<?php

namespace SoftDeleteable\Document;

use TestTool\ObjectManagerTestCase;
use Doctrine\Common\EventManager;
use Fixture\SoftDeleteable\Document\User;
use Gedmo\SoftDeleteable\SoftDeleteableListener;

/**
 * These are tests for SoftDeleteable behavior
 *
 * @author Gustavo Falco <comfortablynumb84@gmail.com>
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @author Patrik Votoček <patrik@votocek.cz>
 * @link http://www.gediminasm.org
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class SoftDeleteableTest extends ObjectManagerTestCase
{
    const USER_CLASS = 'Fixture\SoftDeleteable\Document\User';
    const SOFT_DELETEABLE_FILTER_NAME = 'soft-deleteable';

    private $softDeleteableListener, $dm;

    protected function setUp()
    {
        $evm = new EventManager;
        $evm->addEventSubscriber($this->softDeleteableListener = new SoftDeleteableListener);

        $this->dm = $this->createDocumentManager($evm);
        $this->dm->getConfiguration()->addFilter(
            self::SOFT_DELETEABLE_FILTER_NAME,
            'Gedmo\SoftDeleteable\Filter\ODM\SoftDeleteableFilter'
        );
        $this->dm->getFilterCollection()->enable(self::SOFT_DELETEABLE_FILTER_NAME);
    }

    protected function tearDown()
    {
        $this->releaseDocumentManager($this->dm);
    }

    /**
     * @test
     */
    public function shouldSoftlyDeleteIfColumnNameDifferFromPropertyName()
    {
        $repo = $this->dm->getRepository(self::USER_CLASS);

        $newUser = new User();

        $username = 'test_user';
        $newUser->setUsername($username);

        $this->dm->persist($newUser);
        $this->dm->flush();

        $user = $repo->findOneBy(array('username' => $username));

        $this->assertNull($user->getDeletedAt());

        $this->dm->remove($user);
        $this->dm->flush();

        $user = $repo->findOneBy(array('username' => $username));

        $this->assertNull($user);
    }
    /**
     * Tests the filter by enabling and disabling it between
     * some user persists actions.
     *
     * @test
     */
    function shouldHandleSoftDeleteableFilter()
    {
        $filter = $this->dm->getFilterCollection()->getFilter(self::SOFT_DELETEABLE_FILTER_NAME);
        $filter->disableForDocument(self::USER_CLASS);

        $repo = $this->dm->getRepository(self::USER_CLASS);

        $newUser = new User();
        $username = 'test_user';
        $newUser->setUsername($username);
        $this->dm->persist($newUser);
        $this->dm->flush();

        $user = $repo->findOneBy(array('username' => $username));

        $this->assertNull($user->getDeletedAt());
        $this->dm->remove($user);
        $this->dm->flush();

        $user = $repo->findOneBy(array('username' => $username));

        $this->assertNotNull($user->getDeletedAt());

        $filter->enableForDocument(self::USER_CLASS);

        $user = $repo->findOneBy(array('username' => $username));
        $this->assertNull($user);
    }

    /**
     * @test
     */
    function shouldDispatchPostSoftDeleteEvent()
    {
        $subscriber = $this->getMock(
            "Doctrine\Common\EventSubscriber",
            array(
                "getSubscribedEvents",
                "preSoftDelete",
                "postSoftDelete"
            )
        );

        $subscriber->expects($this->once())
            ->method("getSubscribedEvents")
            ->will($this->returnValue(array(SoftDeleteableListener::PRE_SOFT_DELETE, SoftDeleteableListener::POST_SOFT_DELETE)));

        $subscriber->expects($this->once())
            ->method("preSoftDelete")
            ->with($this->anything());

        $subscriber->expects($this->once())
            ->method("postSoftDelete")
            ->with($this->anything());

        $this->dm->getEventManager()->addEventSubscriber($subscriber);

        $repo = $this->dm->getRepository(self::USER_CLASS);

        $newUser = new User();
        $username = 'test_user';
        $newUser->setUsername($username);

        $this->dm->persist($newUser);
        $this->dm->flush();

        $user = $repo->findOneBy(array('username' => 'test_user'));

        $this->assertNull($user->getDeletedAt());

        $this->dm->remove($user);
        $this->dm->flush();
    }
}
