<?php

namespace App\Modules;

class Module
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getType(): string
    {
        return $this->config['activity_type'] ?? '';
    }

    public function getName(): string
    {
        return $this->config['name'] ?? '';
    }

    public function getVersion(): string
    {
        return $this->config['version'] ?? '1.0.0';
    }

    public function getModels(): array
    {
        return $this->config['models'] ?? [];
    }

    public function getConstraints(): array
    {
        return $this->config['constraints'] ?? [];
    }

    public function getFeatures(): array
    {
        return $this->config['features'] ?? [];
    }

    public function getWorkflow(): array
    {
        return $this->config['workflow'] ?? [];
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function hasFeature(string $feature): bool
    {
        return $this->getFeatures()[$feature] ?? false;
    }

    public function getConstraint(string $key, $default = null)
    {
        return $this->getConstraints()[$key] ?? $default;
    }

    public function getFeature(string $key, $default = null)
    {
        return $this->getFeatures()[$key] ?? $default;
    }
}

