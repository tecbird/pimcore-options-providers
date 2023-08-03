<?php
declare(strict_types=1);

namespace Passioneight\Bundle\PimcoreOptionsProvidersBundle\Service\OptionsProvider;

use function md5;
use function serialize;
use const JSON_ERROR_NONE;
use Exception;
use Passioneight\Bundle\PimcoreOptionsProvidersBundle\Constant\OptionsProviderData;
use Pimcore\Cache\RuntimeCache as Runtime;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;
use RuntimeException;
use function array_key_exists;
use function file_put_contents;
use function is_bool;
use function is_string;
use function json_last_error;
use function json_last_error_msg;
use function var_export;

abstract class AbstractOptionsProvider implements SelectOptionsProviderInterface
{
    public const CACHE_KEY_PREFIX = 'options-provider_';

    protected array $configuration;

    /**
     * {@inheritDoc}
     * In most cases the select has static options, thus, true is the default value. Override if necessary.
     */
    public function hasStaticOptions(array $context, Data $fieldDefinition): bool
    {
        $configuration = $this->loadConfiguration($context, $fieldDefinition);

        $hasStaticOptions = $configuration[OptionsProviderData::STATIC_OPTIONS] ?? null;

        return is_bool($hasStaticOptions) ? $hasStaticOptions : true;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultValue(array $context, Data $fieldDefinition): ?string
    {
        $configuration = $this->loadConfiguration($context, $fieldDefinition);

        return $configuration[OptionsProviderData::DEFAULT_VALUE] ?? null;
    }

    /**
     * @param array $context
     *
     * @return string|null
     */
    protected function getFieldName(array $context): ?string
    {
        return $context[OptionsProviderData::FIELD_NAME] ?? null;
    }

    /**
     * @param array|null $context
     * @param Data|null  $fieldDefinition
     *
     * @return array
     */
    protected function loadConfiguration(?array $context, ?Data $fieldDefinition): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . md5($fieldDefinition ? $fieldDefinition->getOptionsProviderData() ?? $fieldDefinition::class . serialize($context) : serialize($context));

        try {
            $this->configuration = Runtime::get($cacheKey);
        } catch (Exception $exception) {
            $optionsProviderData = $fieldDefinition ? $fieldDefinition->getOptionsProviderData() : $context;
            $optionsProviderData = is_string($optionsProviderData) && !empty($optionsProviderData) ? json_decode($optionsProviderData, true) : $optionsProviderData;
            $this->configuration = $optionsProviderData ?: [];

            Runtime::set($cacheKey, $this->configuration);

            if (JSON_ERROR_NONE !== json_last_error()) {
                $data = $fieldDefinition ? $fieldDefinition->getOptionsProviderData() : $context;
                file_put_contents('/php/public/var/tmp/option_provider.txt', json_last_error_msg(), FILE_APPEND);
                file_put_contents('/php/public/var/tmp/option_provider.txt', var_export($data, true), FILE_APPEND);
                file_put_contents('/php/public/var/tmp/option_provider.txt', var_export((new RuntimeException())->getTraceAsString(), true), FILE_APPEND);
                file_put_contents('/php/public/var/tmp/option_provider.txt', "\n", FILE_APPEND);
            }
        }

        return $this->configuration;
    }

    /**
     * @param string $name
     *
     * @return string|null the value for the configuration with the given name if available, NULL otherwise
     */
    protected function getConfiguration(string $name): ?string
    {
        $configuration = $this->configuration ?: [];

        return array_key_exists($name, $configuration) ? $configuration[$name] : null;
    }

    /**
     * Prepares the given options (e.g., to return a certain format).
     * Override as needed.
     *
     * @param array      $options
     * @param array|null $context
     * @param Data|null  $fieldDefinition
     *
     * @return array
     */
    abstract protected function prepareOptions(array $options, ?array $context, ?Data $fieldDefinition): array;
}
