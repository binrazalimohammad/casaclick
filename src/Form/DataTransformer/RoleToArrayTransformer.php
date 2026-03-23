<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class RoleToArrayTransformer implements DataTransformerInterface
{
    /**
     * Transforms an array (roles) to a string (single role)
     */
    public function transform($roles): ?string
    {
        if (empty($roles) || !is_array($roles)) {
            return 'ROLE_TENANT';
        }
        
        // Return the first role (primary role)
        // Filter out ROLE_TENANT if there's a higher role
        if (in_array('ROLE_ADMIN', $roles)) {
            return 'ROLE_ADMIN';
        }
        if (in_array('ROLE_LANDLORD', $roles)) {
            return 'ROLE_LANDLORD';
        }
        
        return $roles[0] ?? 'ROLE_TENANT';
    }

    /**
     * Transforms a string (single role) back to an array (roles)
     */
    public function reverseTransform($role): array
    {
        if (empty($role)) {
            return ['ROLE_TENANT'];
        }
        
        if (is_array($role)) {
            return $role;
        }
        
        // Return as array with single role
        return [$role];
    }
}

