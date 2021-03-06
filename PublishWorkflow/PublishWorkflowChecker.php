<?php

/*
 * This file is part of the Symfony CMF package.
 *
 * (c) 2011-2015 Symfony CMF
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Cmf\Bundle\CoreBundle\PublishWorkflow;

use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The publish workflow decides if a content is allowed to be shown. Contrary
 * to the symfony core security context, this is even possible without a
 * firewall configured for the current route.
 *
 * The access decision manager is configured to be unanimous by default, and
 * provided with all published voters tagged with cmf_published_voter.
 *
 * If the VIEW attribute is used and there is a firewall in place, there is a
 * check if the current user is granted the bypassing role and if so, he can
 * see even unpublished content.
 *
 * If VIEW_ANONYMOUS is used, the publication check is never bypassed.
 *
 * @author David Buchmann <mail@davidbu.ch>
 */
class PublishWorkflowChecker implements AuthorizationCheckerInterface
{
    /**
     * This attribute means the user is allowed to see this content, either
     * because it is published or because he is granted the bypassingRole.
     */
    const VIEW_ATTRIBUTE = 'VIEW';

    /**
     * This attribute means the content is available for viewing by anonymous
     * users. This can be used where the role based exception from the
     * publication check is not wanted.
     *
     * The bypass role is handled by the workflow checker, the individual
     * voters should treat VIEW and VIEW_ANONYMOUS the same.
     */
    const VIEW_ANONYMOUS_ATTRIBUTE = 'VIEW_ANONYMOUS';

    /**
     * @var bool|string Role allowed to bypass the published check if the
     *                  VIEW attribute is used, or false to never bypass.
     */
    private $bypassingRole;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * @var AccessDecisionManagerInterface
     */
    private $accessDecisionManager;

    /**
     * @var TokenInterface
     */
    private $token;

    /**
     * @param TokenStorageInterface          $tokenStorage
     * @param AuthorizationCheckerInterface  $authorizationChecker
     * @param AccessDecisionManagerInterface $accessDecisionManager Service to do the actual decision.
     * @param bool|string                    $bypassingRole         A role that is allowed to bypass
     *                                                              the published check if we ask for
     *                                                              the VIEW permission. Ignored on
     *                                                              VIEW_ANONYMOUS.
     */
    public function __construct(TokenStorageInterface $tokenStorage, AuthorizationCheckerInterface $authorizationChecker, AccessDecisionManagerInterface $accessDecisionManager, $bypassingRole = false)
    {
        $this->tokenStorage = $tokenStorage;
        $this->authorizationChecker = $authorizationChecker;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->bypassingRole = $bypassingRole;
    }

    /**
     * Checks if the access decision manager supports the given class.
     *
     * @param string $class A class name
     *
     * @return bool true if this decision manager can process the class
     */
    public function supportsClass($class)
    {
        return $this->accessDecisionManager->supportsClass($class);
    }

    /**
     * {@inheritdoc}
     */
    public function isGranted($attributes, $object = null)
    {
        if (!is_array($attributes)) {
            $attributes = array($attributes);
        }

        if (1 === count($attributes)
            && self::VIEW_ATTRIBUTE === reset($attributes)
            && null !== $this->tokenStorage->getToken()
            && $this->authorizationChecker->isGranted($this->bypassingRole)
        ) {
            return true;
        }

        $token = $this->tokenStorage->getToken();

        // not logged in, just check with a dummy token
        if (null === $token) {
            $token = new AnonymousToken('', '');
        }

        return $this->accessDecisionManager->decide($token, $attributes, $object);
    }

    private function getTokenStorage()
    {
        if (false === $this->tokenStorage) {
            if ($this->container->has('security.token_storage')) {
                $this->tokenStorage = $this->container->get('security.token_storage');
            } elseif ($this->container->has('security.context')) {
                // for Symfony <2.6 compatibility
                $this->tokenStorage = $this->container->get('security.context');
            } else {
                $this->tokenStorage = null;
            }
        }

        return $this->tokenStorage;
    }

    private function getAuthorizationChecker()
    {
        if (false === $this->authorizationChecker) {
            if ($this->container->has('security.authorization_checker')) {
                $this->authorizationChecker = $this->container->get('security.authorization_checker');
            } elseif ($this->container->has('security.context')) {
                // for Symfony <2.6 compatibility
                $this->authorizationChecker = $this->container->get('security.context');
            } else {
                $this->authorizationChecker = null;
            }
        }

        return $this->authorizationChecker;
    }
}
