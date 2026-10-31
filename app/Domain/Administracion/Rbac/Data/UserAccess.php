<?php

namespace App\Domain\Administracion\Rbac\Data;

final readonly class UserAccess
{
    /**
     * @param  list<string>  $roleSlugs
     * @param  list<string>  $roleLabels
     * @param  list<string>  $permissionSlugs
     */
    public function __construct(
        public array $roleSlugs,
        public array $roleLabels,
        public array $permissionSlugs,
    ) {}
}
