<?php

declare(strict_types=1);

namespace Scheb\TwoFactorBundle\DependencyInjection\Factory\Security;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;

/**
 * @final
 */
class TwoFactorFactory extends BaseTwoFactorFactory implements SecurityFactoryInterface {
}
