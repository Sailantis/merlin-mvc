<?php

namespace Azera\Orm;

use Azera\AppContext;

/**
 * Base class for MongoDB-backed objects (pairs with #[Document]).
 *
 * FACADE over the {@see EntityManager}: save()/delete() delegate to the
 * EM's write pipeline (persist + flush), so documents and SQL models go
 * through the SAME diff -> order -> transaction path and land in the SAME
 * request-scoped heap. The #[Document] attribute's storeRole selects the
 * StoreManager role (MongoStore once it lands; tenancy via per-tenant
 * roles).
 *
 * The EM's heap-node diff is authoritative — hydrated documents are
 * heap-tracked, so persist() schedules an UPDATE only when fields actually
 * changed.
 */
abstract class Document
{
    /**
     * Which StoreManager role resolves the backend for this document.
     * Mirrors the #[Document(storeRole: ...)] attribute; the attribute is
     * the authority when both are present (it compiles into metadata).
     */
    public function storeRole(): string
    {
        return Metadata::for(static::class)['storeRole'] ?? 'default';
    }

    /**
     * Save via the EM: INSERT when untracked, diff-UPDATE when managed.
     * The EM's heap-node diff is authoritative (hydrated documents are
     * heap-tracked with a baseline snapshot), so we schedule FIRST,
     * check whether anything was actually queued, and only then flush.
     */
    public function save(): bool
    {
        $em = AppContext::instance()->entityManager();

        $em->persist($this);
        $node      = $em->heap()->find($this);
        $scheduled = $node !== null && $node->isScheduled();

        if ($scheduled) {
            $em->flush();
        }

        return $scheduled;
    }

    public function delete(): bool
    {
        $meta    = Metadata::for(static::class);
        $pkField = $this->pkField($meta);

        if (($this->{$pkField} ?? null) === null) {
            throw new \RuntimeException('Cannot delete without PK');
        }

        AppContext::instance()->entityManager()->remove($this)->flush();
        return true;
    }

    private function pkField(array $meta): string
    {
        foreach ($meta['columns'] as $field => $col) {
            if ($col['pk']) {
                return $field;
            }
        }

        return 'id';
    }

    /* -------------------------------------------------------------
     *  DIRTY STATE
     * ------------------------------------------------------------- */

    /**
     * Whether any field differs from the heap baseline (untracked entity:
     * true when any metadata column has a set value).
     */
    public function hasChanged(): bool
    {
        return AppContext::instance()->entityManager()->isDirty($this);
    }

    /**
     * Field-name-keyed map of values that differ from the heap baseline
     * (untracked entity: all set values).
     *
     * @return array<string, mixed>
     */
    public function changedData(): array
    {
        return AppContext::instance()->entityManager()->dirtyData($this);
    }

    /**
     * Revert all properties to the values recorded in the heap node
     * snapshot (the loadState() replacement). No-op for untracked entities.
     */
    public function loadState(): static
    {
        AppContext::instance()->entityManager()->revert($this);
        return $this;
    }

}