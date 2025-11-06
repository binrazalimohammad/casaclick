<?php

namespace App\Tests\Controller;

use App\Entity\Landlord;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LandlordControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $landlordRepository;
    private string $path = '/landlord/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->landlordRepository = $this->manager->getRepository(Landlord::class);

        foreach ($this->landlordRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Landlord index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'landlord[FirstName]' => 'Testing',
            'landlord[LastName]' => 'Testing',
            'landlord[Email]' => 'Testing',
            'landlord[Phone]' => 'Testing',
            'landlord[Address]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->landlordRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new Landlord();
        $fixture->setFirstName('My Title');
        $fixture->setLastName('My Title');
        $fixture->setEmail('My Title');
        $fixture->setPhone('My Title');
        $fixture->setAddress('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Landlord');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new Landlord();
        $fixture->setFirstName('Value');
        $fixture->setLastName('Value');
        $fixture->setEmail('Value');
        $fixture->setPhone('Value');
        $fixture->setAddress('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'landlord[FirstName]' => 'Something New',
            'landlord[LastName]' => 'Something New',
            'landlord[Email]' => 'Something New',
            'landlord[Phone]' => 'Something New',
            'landlord[Address]' => 'Something New',
        ]);

        self::assertResponseRedirects('/landlord/');

        $fixture = $this->landlordRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getFirstName());
        self::assertSame('Something New', $fixture[0]->getLastName());
        self::assertSame('Something New', $fixture[0]->getEmail());
        self::assertSame('Something New', $fixture[0]->getPhone());
        self::assertSame('Something New', $fixture[0]->getAddress());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new Landlord();
        $fixture->setFirstName('Value');
        $fixture->setLastName('Value');
        $fixture->setEmail('Value');
        $fixture->setPhone('Value');
        $fixture->setAddress('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/landlord/');
        self::assertSame(0, $this->landlordRepository->count([]));
    }
}
