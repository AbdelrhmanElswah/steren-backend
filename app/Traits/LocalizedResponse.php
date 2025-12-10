<?php

namespace App\Traits;

trait LocalizedResponse
{
    /**
     * Get localized field value based on language parameter
     * 
     * @param object $model The model instance
     * @param string $baseField The base field name (e.g., 'name', 'title', 'description')
     * @param string|null $lang The language code ('ar', 'kr', or null for default)
     * @return string|null The localized value or fallback to base field
     */
    protected function getLocalizedField($model, string $baseField, ?string $lang = null): ?string
    {
        if (!$lang) {
            return $model->{$baseField} ?? null;
        }

        $localizedField = $baseField . '_' . $lang;
        
        // Return localized field if exists and not empty, otherwise fallback to base field
        $localizedValue = $model->{$localizedField} ?? null;
        
        if (!empty($localizedValue)) {
            return $localizedValue;
        }
        
        // Fallback to base field
        return $model->{$baseField} ?? null;
    }

    /**
     * Get language from request (query parameter 'lang')
     * Valid values: 'en', 'ar', 'kr', or null (default)
     * Note: 'en' is accepted but returns null (uses base field for English)
     * 
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    protected function getLanguageFromRequest($request): ?string
    {
        $lang = $request->query('lang');
        
        // Validate language code - accept en, ar, kr
        if (in_array($lang, ['en', 'ar', 'kr'])) {
            // Return null for 'en' since we use base field for English
            return $lang === 'en' ? null : $lang;
        }
        
        return null;
    }
}

