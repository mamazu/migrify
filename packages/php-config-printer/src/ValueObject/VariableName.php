<?php

declare(strict_types=1);

namespace Migrify\PhpConfigPrinter\ValueObject;

final class VariableName
{
    /**
     * @var string
     */
    public const CONTAINER_CONFIGURATOR = 'containerConfigurator';

    /**
     * @var string
     */
    public const ROUTING_CONFIGURATOR = 'routingConfigurator';

    /**
     * @var string
     */
    public const SERVICES = 'services';

    /**
     * @var string
     */
    public const PARAMETERS = 'parameters';
}
