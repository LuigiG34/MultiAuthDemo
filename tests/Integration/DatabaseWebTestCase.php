<?php

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;

abstract class DatabaseWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();

        // reset DB schema
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $meta = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($meta);
        $tool->createSchema($meta);

        // start a session so CSRF work
        $sessionFactory = static::getContainer()->get('session.factory');
        $session = $sessionFactory->createSession();
        $session->start();

        // inject session cookie into the test client
        $this->client->getCookieJar()->set(
            new Cookie($session->getName(), $session->getId())
        );

        // push a dummy request with session into the RequestStack
        $req = Request::create('/_test_boot');
        $req->setSession($session);
        static::getContainer()->get('request_stack')->push($req);
    }

    protected function tearDown(): void
    {
        $this->em->clear();
        $this->em->close();
        unset($this->em);
        self::ensureKernelShutdown();
        parent::tearDown();
    }
}
