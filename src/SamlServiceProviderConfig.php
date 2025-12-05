<?php

namespace CodeGreenCreative\SamlIdp;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SamlServiceProviderConfig
{
    protected ?string $spModel;

    public function __construct()
    {
        $this->spModel = $this->resolveSpModel();
    }

    protected function resolveSpModel(): ?string
    {
        $spModel = config('samlidp.sp_model');
        return is_string($spModel) && class_exists($spModel) ? $spModel : null;
    }

    public function getValue(string $spId, string $key): mixed
    {
        if ($this->spModel) {
            $sp = $this->spModel::find($spId);
            if (!$sp) {
                return null;
            }
            return $sp->$key;
        }

        return Arr::get(config('samlidp.sp'), "$spId.$key");
    }

    /**
     * Get all SPs.
     */
    public function all(): array
    {
        if ($this->spModel) {
            return $this->spModel::all()->toArray();
        }

        return config('samlidp.sp', []);
    }

    /**
     * Check if SP model is being used.
     */
    public function isModel(): bool
    {
        return $this->spModel !== null;
    }

    /**
     * Find a service provider by ID.
     * Returns a normalized array representation regardless of source.
     *
     * @param string $spId The base64 encoded ACS URL
     * @return array|null The SP data or null if not found
     */
    public function find(string $spId): ?array
    {
        if ($this->spModel) {
            $sp = $this->spModel::find($spId);
            if (!$sp) {
                return null;
            }
            return $sp->toArray();
        }

        $sp = config("samlidp.sp.{$spId}");
        if (!$sp) {
            return null;
        }

        return array_merge(['id' => $spId], $sp);
    }

    /**
     * Check if a service provider supports Single Logout.
     *
     * @param string $spId The base64 encoded ACS URL
     * @return bool True if SP has logout URL configured
     */
    public function hasLogout(string $spId): bool
    {
        $logout = $this->getValue($spId, 'logout');
        return !empty($logout);
    }

    /**
     * Get the logout URL for a service provider with query parameters appended.
     *
     * @param string $spId The base64 encoded ACS URL
     * @return string|null The fully qualified logout URL or null if not configured
     */
    public function getLogoutUrl(string $spId): ?string
    {
        $logout = $this->getValue($spId, 'logout');

        if (empty($logout)) {
            return null;
        }

        $queryParams = $this->getValue($spId, 'query_params');

        // Handle null (use defaults)
        if ($queryParams === null) {
            $queryParams = [
                'idp' => config('app.url'),
            ];
        }

        // Handle false (explicitly disabled)
        if ($queryParams === false) {
            return $logout;
        }

        // Handle empty array (no params)
        if (is_array($queryParams) && empty($queryParams)) {
            return $logout;
        }

        // Append query parameters
        if (is_array($queryParams) && !empty($queryParams)) {
            if (!parse_url($logout, PHP_URL_QUERY)) {
                $logout = Str::finish(url($logout), '?') . Arr::query($queryParams);
            } else {
                $logout .= '&' . Arr::query($queryParams);
            }
        }

        return $logout;
    }

    /**
     * Get all service providers with their IDs as keys.
     * Returns normalized array structure regardless of source.
     *
     * @return array Associative array of SP ID => SP data
     */
    public function allWithKeys(): array
    {
        if ($this->spModel) {
            $sps = $this->spModel::all();
            $result = [];
            foreach ($sps as $sp) {
                $result[$sp->id] = $sp->toArray();
            }
            return $result;
        }

        $sps = config('samlidp.sp', []);

        // Add 'id' field to each SP for consistency
        $result = [];
        foreach ($sps as $id => $sp) {
            $result[$id] = array_merge(['id' => $id], $sp);
        }

        return $result;
    }
}
