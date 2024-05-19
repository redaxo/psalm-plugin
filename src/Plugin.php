<?php

declare(strict_types=1);

namespace Redaxo\PsalmPlugin;

use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use Redaxo\PsalmPlugin\Provider\RexTypeReturnProvider;
use SimpleXMLElement;

/**
 * @psalm-suppress UnusedClass
 */
class Plugin implements PluginEntryPointInterface
{
    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        require_once __DIR__ . '/Provider/RexTypeReturnProvider.php';

        $registration->registerHooksFromClass(RexTypeReturnProvider::class);
    }
}
