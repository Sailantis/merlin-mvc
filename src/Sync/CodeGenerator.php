<?php
namespace Azera\Sync;

class CodeGenerator
{
    private string $indent = '    '; // 4 spaces

    public function applyDiff(ParsedModel $model, array $operations): string
    {
        $code = file_get_contents($model->filePath);

        // Separate additions and accessor inserts from in-place modifications.
        // Modifications are applied first (bottom-to-top order ensures no drift when
        // we use regex / str_replace). Additions are appended in one block last.
        $adds = array_filter($operations, fn($op) => $op instanceof AddProperty);
        $accessors = array_filter($operations, fn($op) => $op instanceof AddAccessor);
        $updates = array_filter($operations, fn($op) => !($op instanceof AddProperty) && !($op instanceof AddAccessor));

        foreach ($updates as $op) {
            if ($op instanceof RemoveProperty) {
                $code = $this->markDeprecated($code, $model, $op);
            } elseif ($op instanceof UpdatePropertyType) {
                $code = $this->updatePropertyType($code, $model, $op);
            } elseif ($op instanceof UpdatePropertyComment) {
                $code = $this->updatePropertyComment($code, $model, $op);
            } elseif ($op instanceof UpdateClassComment) {
                $code = $this->updateClassComment($code, $model, $op);
            }
        }

        if (!empty($adds) || !empty($accessors)) {
            $code = $this->insertPropertiesAndAccessors(
                $code,
                $model,
                array_values($adds),
                array_values($accessors)
            );
        }

        return $code;
    }

    // -------------------------------------------------------------------------
    //  Insert new properties
    // -------------------------------------------------------------------------

    private function insertPropertiesAndAccessors(string $code, ParsedModel $model, array $adds, array $accessors): string
    {
        $block = '';

        foreach ($adds as $op) {
            /** @var AddProperty $op */
            if ($op->comment) {
                $block .= "\n{$this->indent}/** {$op->comment} */";
            }
            $block .= "\n{$this->indent}{$op->visibility} {$op->type} \${$op->property};";
        }

        foreach ($accessors as $op) {
            /** @var AddAccessor $op */
            $block .= $this->buildAccessorBlock($op);
        }

        // insertionOffset points to the char right after the last ';'
        // (or right after the opening '{' for empty classes).
        // We insert the block there; the existing whitespace/closing brace follows.
        return substr_replace($code, $block, $model->insertionOffset, 0);
    }

    private function buildAccessorBlock(AddAccessor $op): string
    {
        $type = $op->phpType;
        $vis = $op->visibility;
        $base = ucfirst($op->methodName);
        $prop = $op->property;
        $i = $this->indent;
        $ii = $i . $i;

        $getter =
            "\n" .
            "\n{$i}/**" .
            "\n{$i} * @return {$type}" .
            "\n{$i} */" .
            "\n{$i}{$vis} function get{$base}(): {$type}" .
            "\n{$i}{" .
            "\n{$ii}return \$this->{$prop};" .
            "\n{$i}}";

        $setter =
            "\n" .
            "\n{$i}/**" .
            "\n{$i} * @param {$type} \$value" .
            "\n{$i} * @return static" .
            "\n{$i} */" .
            "\n{$i}{$vis} function set{$base}({$type} \$value): static" .
            "\n{$i}{" .
            "\n{$ii}\$this->{$prop} = \$value;" .
            "\n{$ii}return \$this;" .
            "\n{$i}}";

        return $getter . $setter;
    }

    // -------------------------------------------------------------------------
    //  Mark property @deprecated
    // -------------------------------------------------------------------------

    private function markDeprecated(string $code, ParsedModel $model, RemoveProperty $op): string
    {
        $prop = $model->properties[$op->property] ?? null;
        if (!$prop) {
            return $code;
        }

        if ($prop->docComment) {
            // The docblock text is known exactly from reflection – add @deprecated tag.
            $newDoc = $this->injectDeprecatedTag($prop->docComment);
            return str_replace($prop->docComment, $newDoc, $code);
        }

        // No docblock – prepend one before the property declaration line.
        $escaped = preg_quote($op->property, '/');
        return preg_replace(
            '/([ \t]*)((?:(?:public|protected|private|readonly)\s+)*\??[\w|\\\\]*\s+\$' . $escaped . ';)/m',
            "$1/** @deprecated Column removed from DB */\n$1$2",
            $code,
            1
        );
    }

    private function injectDeprecatedTag(string $docComment): string
    {
        // Insert before the closing */ on its own line.
        if (str_contains($docComment, "\n")) {
            return preg_replace('/(\s*\*\/)$/', "\n * @deprecated Column removed from DB\n */", $docComment);
        }

        // Single-line /** … */ → expand to multi-line.
        $inner = trim(preg_replace('/^\/\*\*\s*|\s*\*\/$/', '', $docComment));
        return "/**\n * {$inner}\n * @deprecated Column removed from DB\n */";
    }

    // -------------------------------------------------------------------------
    //  Update property type
    // -------------------------------------------------------------------------

    private function updatePropertyType(string $code, ParsedModel $model, UpdatePropertyType $op): string
    {
        $prop = $model->properties[$op->property] ?? null;
        if (!$prop) {
            return $code;
        }

        $escapedName = preg_quote($op->property, '/');
        $escapedOld = preg_quote($op->oldType ?? '', '/');

        if ($op->oldType) {
            // Replace the exact old type before $name.
            $code = preg_replace(
                '/(\b(?:public|protected|private|readonly)\b(?:\s+\b(?:public|protected|private|readonly)\b)*\s+)' . $escapedOld . '(\s+\$' . $escapedName . '\b)/',
                '$1' . $op->newType . '$2',
                $code,
                1
            );
        } else {
            // No previous type: insert the new type before $name.
            $code = preg_replace(
                '/(\b(?:public|protected|private|readonly)\b(?:\s+\b(?:public|protected|private|readonly)\b)*\s+)(\$' . $escapedName . '\b)/',
                '$1' . $op->newType . ' $2',
                $code,
                1
            );
        }

        return $code;
    }

    // -------------------------------------------------------------------------
    //  Update property comment / docblock
    // -------------------------------------------------------------------------

    private function updatePropertyComment(string $code, ParsedModel $model, UpdatePropertyComment $op): string
    {
        $prop = $model->properties[$op->property] ?? null;
        if (!$prop) {
            return $code;
        }

        $newDoc = $op->newComment ? "/** {$op->newComment} */" : null;

        if ($prop->docComment && $newDoc) {
            return str_replace($prop->docComment, $newDoc, $code);
        }

        if ($prop->docComment && !$newDoc) {
            // Remove the existing docblock (and trailing whitespace up to next token).
            $escaped = preg_quote($prop->docComment, '/');
            return preg_replace('/' . $escaped . '\s*/', '', $code, 1);
        }

        if (!$prop->docComment && $newDoc) {
            // Prepend docblock before the property line.
            $escapedName = preg_quote($op->property, '/');
            return preg_replace(
                '/([ \t]*)((?:(?:public|protected|private|readonly)\s+)*\??[\w|\\\\]*\s+\$' . $escapedName . ';)/m',
                "$1{$newDoc}\n$1$2",
                $code,
                1
            );
        }

        return $code;
    }

    // -------------------------------------------------------------------------
    //  Update class docblock
    // -------------------------------------------------------------------------

    private function updateClassComment(string $code, ParsedModel $model, UpdateClassComment $op): string
    {
        $newDoc = $op->newComment ? "/**\n * {$op->newComment}\n */" : null;

        if ($model->classComment && $newDoc) {
            return str_replace($model->classComment, $newDoc, $code);
        }

        if ($model->classComment && !$newDoc) {
            // Remove the class docblock.
            $escaped = preg_quote($model->classComment, '/');
            return preg_replace('/' . $escaped . '\s*/', '', $code, 1);
        }

        if (!$model->classComment && $newDoc) {
            // Insert before the 'class' keyword.
            return preg_replace('/(\bclass\b)/', $newDoc . "\n$1", $code, 1);
        }

        return $code;
    }
}
