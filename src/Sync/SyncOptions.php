<?php
namespace Azera\Sync;

/**
 * Configuration options that control the behavior of the model-sync process.
 */
class SyncOptions
{
    /**
     * @param bool   $generateAccessors  When true a camelized getter/setter method pair is
     *                                   generated for every newly-added property.
     * @param string $fieldVisibility    Visibility modifier applied to generated properties:
     *                                   'public', 'protected', or 'private'.
     * @param bool   $deprecate          When false, properties whose columns have been removed
     *                                   are left untouched instead of being tagged @deprecated.
     */
    public function __construct(
        public bool $generateAccessors = false,
        public string $fieldVisibility = 'public',
        public bool $deprecate = true,
    ) {
    }
}
