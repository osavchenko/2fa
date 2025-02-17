<?php

declare(strict_types=1);

namespace Scheb\TwoFactorBundle\Tests\Security\TwoFactor\Provider;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Exception\UnexpectedTokenException;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\PreparationRecorderInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderPreparationListener;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderRegistry;
use Scheb\TwoFactorBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;

class TwoFactorProviderPreparationListenerTest extends TestCase
{
    private const FIREWALL_NAME = 'firewallName';
    private const CURRENT_PROVIDER_NAME = 'currentProviderName';

    /**
     * @var MockObject|TwoFactorProviderRegistry
     */
    private $providerRegistry;

    /**
     * @var MockObject|Request
     */
    private $request;

    /**
     * @var MockObject|PreparationRecorderInterface
     */
    private $preparationRecorder;

    /**
     * @var MockObject|TwoFactorToken
     */
    private $token;

    /**
     * @var
     */
    private $user;

    /**
     * @var TwoFactorProviderPreparationListener
     */
    private $listener;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Request::class);
        $this->user = new \stdClass();
        $this->token = $this->createMock(TwoFactorToken::class);
        $this->token
            ->expects($this->any())
            ->method('getProviderKey')
            ->willReturn(self::FIREWALL_NAME);
        $this->token
            ->expects($this->any())
            ->method('getCurrentTwoFactorProvider')
            ->willReturn(self::CURRENT_PROVIDER_NAME);
        $this->token
            ->expects($this->any())
            ->method('getUser')
            ->willReturn($this->user);

        $this->preparationRecorder = $this->createMock(PreparationRecorderInterface::class);

        $this->providerRegistry = $this->createMock(TwoFactorProviderRegistry::class);
    }

    private function initTwoFactorProviderPreparationListener($prepareOnLogin, $prepareOnAccessDenied): void
    {
        $this->listener = new TwoFactorProviderPreparationListener(
            $this->providerRegistry,
            $this->preparationRecorder,
            $this->createMock(LoggerInterface::class),
            self::FIREWALL_NAME,
            $prepareOnLogin,
            $prepareOnAccessDenied
        );
    }

    private function createTwoFactorAuthenticationEvent(): TwoFactorAuthenticationEvent
    {
        return new TwoFactorAuthenticationEvent($this->request, $this->token);
    }

    private function createAuthenticationEvent(): AuthenticationEvent
    {
        return new AuthenticationEvent($this->token);
    }

    private function createResponseEvent(): ResponseEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $response = $this->createMock(Response::class);

        if (\defined('Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST')) {
            // Compatibility for Symfony >= 5.3
            $requestType = HttpKernelInterface::MAIN_REQUEST;
        } else {
            $requestType = HttpKernelInterface::MASTER_REQUEST;
        }

        // Class is final, have to use a real instance instead of a mock
        return new ResponseEvent($kernel, $this->request, $requestType, $response);
    }

    private function expectPrepareCurrentProvider(): void
    {
        $twoFactorProvider = $this->createMock(TwoFactorProviderInterface::class);
        $this->preparationRecorder
            ->expects($this->once())
            ->method('isTwoFactorProviderPrepared')
            ->with(self::FIREWALL_NAME, self::CURRENT_PROVIDER_NAME)
            ->willReturn(false);

        $this->providerRegistry
            ->expects($this->once())
            ->method('getProvider')
            ->with(self::CURRENT_PROVIDER_NAME)
            ->willReturn($twoFactorProvider);

        $this->preparationRecorder
            ->expects($this->once())
            ->method('setTwoFactorProviderPrepared')
            ->with(self::FIREWALL_NAME, self::CURRENT_PROVIDER_NAME);

        $twoFactorProvider
            ->expects($this->once())
            ->method('prepareAuthentication')
            ->with($this->identicalTo($this->user));
    }

    private function expectNotPrepareCurrentProvider(): void
    {
        $this->preparationRecorder
            ->expects($this->never())
            ->method($this->anything());

        $this->providerRegistry
            ->expects($this->never())
            ->method('getProvider');
    }

    /**
     * @test
     */
    public function onLogin_optionPrepareOnLoginTrue_twoFactorProviderIsPrepared(): void
    {
        $this->initTwoFactorProviderPreparationListener(true, false);
        $event = $this->createAuthenticationEvent();

        $this->expectPrepareCurrentProvider();

        $this->listener->onLogin($event);
        $this->listener->onKernelResponse($this->createResponseEvent());
    }

    /**
     * @test
     */
    public function onLogin_optionPrepareOnLoginFalse_twoFactorProviderIsNotPrepared(): void
    {
        $this->initTwoFactorProviderPreparationListener(false, false);
        $event = $this->createAuthenticationEvent();

        $this->expectNotPrepareCurrentProvider();

        $this->listener->onLogin($event);
        $this->listener->onKernelResponse($this->createResponseEvent());
    }

    /**
     * @test
     */
    public function onAccessDenied_optionPrepareOnAccessDeniedTrue_twoFactorProviderIsPrepared(): void
    {
        $this->initTwoFactorProviderPreparationListener(false, true);
        $event = $this->createTwoFactorAuthenticationEvent();

        $this->expectPrepareCurrentProvider();

        $this->listener->onAccessDenied($event);
        $this->listener->onKernelResponse($this->createResponseEvent());
    }

    /**
     * @test
     */
    public function onAccessDenied_optionPrepareOnAccessDeniedFalse_twoFactorProviderIsNotPrepared(): void
    {
        $this->initTwoFactorProviderPreparationListener(false, false);
        $event = $this->createTwoFactorAuthenticationEvent();

        $this->expectNotPrepareCurrentProvider();

        $this->listener->onAccessDenied($event);
        $this->listener->onKernelResponse($this->createResponseEvent());
    }

    /**
     * @test
     */
    public function onTwoFactorForm_onEvent_twoFactorProviderIsPrepared(): void
    {
        $this->initTwoFactorProviderPreparationListener(false, false);
        $event = $this->createTwoFactorAuthenticationEvent();

        $this->expectPrepareCurrentProvider();

        $this->listener->onTwoFactorForm($event);
        $this->listener->onKernelResponse($this->createResponseEvent());

        // A second request shouldn't trigger preparation
        $this->listener->onKernelResponse($this->createResponseEvent());
    }

    /**
     * @test
     */
    public function onKernelResponse_providerAlreadyPrepared_saveSession(): void
    {
        $this->initTwoFactorProviderPreparationListener(true, true);
        $event = $this->createTwoFactorAuthenticationEvent();

        $this->preparationRecorder
            ->expects($this->once())
            ->method('isTwoFactorProviderPrepared')
            ->with(self::FIREWALL_NAME, self::CURRENT_PROVIDER_NAME)
            ->willReturn(true);

        $this->preparationRecorder
            ->expects($this->never())
            ->method('setTwoFactorProviderPrepared');

        $this->providerRegistry
            ->expects($this->never())
            ->method('getProvider');

        $this->listener->onTwoFactorForm($event);
        $this->listener->onKernelResponse($this->createResponseEvent());
    }

    /**
     * @test
     */
    public function onKernelResponse_recorderThrowsUnexpectedTokenException_doNothing(): void
    {
        $this->initTwoFactorProviderPreparationListener(false, false);
        $event = $this->createTwoFactorAuthenticationEvent();

        $this->preparationRecorder
            ->expects($this->any())
            ->method('isTwoFactorProviderPrepared')
            ->willThrowException(new UnexpectedTokenException());

        $this->preparationRecorder
            ->expects($this->never())
            ->method('setTwoFactorProviderPrepared');

        $this->listener->onTwoFactorForm($event);
        $this->listener->onKernelResponse($this->createResponseEvent());
    }
}
