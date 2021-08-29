<?php

declare(strict_types=1);

namespace Scheb\TwoFactorBundle\Security\Authentication;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @final
 */
class AuthenticationTrustResolver implements AuthenticationTrustResolverInterface
{
    /**
     * @var AuthenticationTrustResolverInterface
     */
    private $decoratedTrustResolver;

    public function __construct(AuthenticationTrustResolverInterface $decoratedTrustResolver)
    {
        $this->decoratedTrustResolver = $decoratedTrustResolver;
    }

    // Compatibility with Symfony < 6.0
    public function isAnonymous(TokenInterface $token = null): bool
    {
        if (method_exists($this->decoratedTrustResolver, 'isAnonymous')) {
            return $this->decoratedTrustResolver->isAnonymous($token);
        }
        if (method_exists($this->decoratedTrustResolver, 'isAuthenticated')) {
            return !$this->decoratedTrustResolver->isAuthenticated($token);
        }

        throw new \RuntimeException('Neither "isAnonymous" nor "isAuthenticated" declared on the decorated AuthenticationTrustResolverInterface');
    }

    public function isRememberMe(TokenInterface $token = null): bool
    {
        return $this->decoratedTrustResolver->isRememberMe($token);
    }

    public function isFullFledged(TokenInterface $token = null): bool
    {
        return !$this->isTwoFactorToken($token) && $this->decoratedTrustResolver->isFullFledged($token);
    }

    public function isAuthenticated(TokenInterface $token = null): bool
    {
        return $this->decoratedTrustResolver->isAuthenticated($token);
    }

    private function isTwoFactorToken(?TokenInterface $token): bool
    {
        return $token instanceof TwoFactorTokenInterface;
    }
}
