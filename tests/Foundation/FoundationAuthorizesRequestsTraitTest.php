<?php

namespace Illuminate\Tests\Foundation;

use PHPUnit\Framework\TestCase;
use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;

class FoundationAuthorizesRequestsTraitTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function testBasicGateCheck()
    {
        unset($_SERVER['_test.authorizes.trait']);

        $gate = $this->getBasicGate();

        $gate->define('baz', function () {
            $_SERVER['_test.authorizes.trait'] = true;

            return true;
        });

        $response = (new FoundationTestAuthorizeTraitClass)->authorize('baz');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($_SERVER['_test.authorizes.trait']);
    }

    public function testExceptionIsThrownIfGateCheckFails()
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('This action is unauthorized.');

        $gate = $this->getBasicGate();

        $gate->define('baz', function () {
            return false;
        });

        (new FoundationTestAuthorizeTraitClass)->authorize('baz');
    }

    public function testPoliciesMayBeCalled()
    {
        unset($_SERVER['_test.authorizes.trait.policy']);

        $gate = $this->getBasicGate();

        $gate->policy(FoundationAuthorizesRequestTestClass::class, FoundationAuthorizesRequestTestPolicy::class);

        $response = (new FoundationTestAuthorizeTraitClass)->authorize('update', new FoundationAuthorizesRequestTestClass);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($_SERVER['_test.authorizes.trait.policy']);
    }

    public function testPolicyMethodMayBeGuessedPassingModelInstance()
    {
        unset($_SERVER['_test.authorizes.trait.policy']);

        $gate = $this->getBasicGate();

        $gate->policy(FoundationAuthorizesRequestTestClass::class, FoundationAuthorizesRequestTestPolicy::class);

        $response = (new FoundationTestAuthorizeTraitClass)->authorize(new FoundationAuthorizesRequestTestClass);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($_SERVER['_test.authorizes.trait.policy']);
    }

    public function testPolicyMethodMayBeGuessedPassingClassName()
    {
        unset($_SERVER['_test.authorizes.trait.policy']);

        $gate = $this->getBasicGate();

        $gate->policy('\\'.FoundationAuthorizesRequestTestClass::class, FoundationAuthorizesRequestTestPolicy::class);

        $response = (new FoundationTestAuthorizeTraitClass)->authorize('\\'.FoundationAuthorizesRequestTestClass::class);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($_SERVER['_test.authorizes.trait.policy']);
    }

    public function testPolicyMethodMayBeGuessedAndNormalized()
    {
        unset($_SERVER['_test.authorizes.trait.policy']);

        $gate = $this->getBasicGate();

        $gate->policy(FoundationAuthorizesRequestTestClass::class, FoundationAuthorizesRequestTestPolicy::class);

        (new FoundationTestAuthorizeTraitClass)->store(new FoundationAuthorizesRequestTestClass);

        $this->assertTrue($_SERVER['_test.authorizes.trait.policy']);
    }

    public function getBasicGate()
    {
        $container = Container::setInstance(new Container);

        $gate = new Gate($container, function () {
            return (object) ['id' => 1];
        });

        $container->instance(GateContract::class, $gate);

        return $gate;
    }
}

class FoundationAuthorizesRequestTestClass
{
    //
}

class FoundationAuthorizesRequestTestPolicy
{
    public function create()
    {
        $_SERVER['_test.authorizes.trait.policy'] = true;

        return true;
    }

    public function update()
    {
        $_SERVER['_test.authorizes.trait.policy'] = true;

        return true;
    }

    public function test_policy_method_may_be_guessed_passing_model_instance()
    {
        $_SERVER['_test.authorizes.trait.policy'] = true;

        return true;
    }

    public function test_policy_method_may_be_guessed_passing_class_name()
    {
        $_SERVER['_test.authorizes.trait.policy'] = true;

        return true;
    }
}

class FoundationTestAuthorizeTraitClass
{
    use AuthorizesRequests;

    public function store($object)
    {
        $this->authorize($object);
    }
}
