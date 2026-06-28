<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

trait MapsSchoolManagementErrors
{
    private function managementError(InvalidArgumentException $e): JsonResponse
    {
        $message = $e->getMessage();
        $name = null;

        if (str_contains($message, ':')) {
            [$code, $name] = explode(':', $message, 2);
        } else {
            $code = $message;
        }

        $key = match ($code) {
            'class_name_duplicate' => 'edubridge.class_name_duplicate',
            'section_name_duplicate' => 'edubridge.section_name_duplicate',
            'class_has_students' => 'edubridge.class_has_students',
            'section_has_students' => 'edubridge.section_has_students',
            default => 'edubridge.generic_error',
        };

        $params = $name !== null ? ['name' => $name] : [];

        return response()->json(['message' => __($key, $params)], 422);
    }
}
