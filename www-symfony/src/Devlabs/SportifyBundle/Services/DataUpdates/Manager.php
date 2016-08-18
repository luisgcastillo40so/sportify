<?php

namespace Devlabs\SportifyBundle\Services\DataUpdates;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\EntityManager;
use Devlabs\SportifyBundle\Entity\Tournament;

/**
 * Class Manager
 * @package Devlabs\SportifyBundle\Services\DataUpdates
 */
class Manager
{
    use ContainerAwareTrait;

    private $em;
    private $footballApi;
    private $dataFetcher;
    private $dataParser;
    private $dataImporter;

    public function __construct(ContainerInterface $container, EntityManager $entityManager, $footballApi)
    {
        $this->container = $container;
        $this->em = $entityManager;
        $this->footballApi = $footballApi;

        $this->dataFetcher = $this->container->get('app.data_updates.fetchers.'.$footballApi);
        $this->dataParser = $this->container->get('app.data_updates.parsers.'.$footballApi);
        $this->dataImporter = $this->container->get('app.data_updates.importer');
    }

    /**
     * Method for updating teams via API Fetch, Parse and Import services
     *
     * @param Tournament $tournament
     */
    public function updateTeamsByTournament(Tournament $tournament)
    {
        $apiMapping = $this->em->getRepository('DevlabsSportifyBundle:ApiMapping')
            ->getByEntityAndApiProvider($tournament, 'Tournament', $this->footballApi);
        $apiTournamentId = $apiMapping->getApiObjectId();

        $fetchedTeams = $this->dataFetcher->fetchTeamsByTournament($apiTournamentId);

        // parse the fetched data
        $parsedTeams = $this->dataParser->parseTeams($fetchedTeams);

        // invoke Importer service and import parsed data
        $this->dataImporter->importTeams($parsedTeams, $tournament, $this->footballApi);
    }

    /**
     * Method for updating fixtures data via API Fetch, Parse and Import services
     * for a given time range (start date and end date)
     */
    public function updateFixtures($dateFrom, $dateTo)
    {
        // get all tournaments
        $tournaments = $this->em->getRepository('DevlabsSportifyBundle:Tournament')->findAll();

        // return if no tournaments in db
        if (!$tournaments) {
           return;
        }

        $status = array();
        $status['total_fetched'] = 0;
        $status['total_added'] = 0;
        $status['total_updated'] = 0;

        // iterate the following actions for each tournament
        foreach ($tournaments as $tournament) {
            $apiMapping = $this->em->getRepository('DevlabsSportifyBundle:ApiMapping')
                ->getByEntityAndApiProvider($tournament, 'Tournament', $this->footballApi);

            // skip tournament if finished or there is no API mapping for it
            if (($tournament->getChampionTeamId() !== null) || (!$apiMapping)) continue;

            $status[$tournament->getId()]['name'] = $tournament->getName();

            // get the API tournament ID
            $apiTournamentId = $apiMapping->getApiObjectId();

            // fetch fixture data from API for given time range
            $fetchedFixtures = $this->dataFetcher->fetchFixturesByTournamentAndTimeRange($apiTournamentId, $dateFrom, $dateTo);

            // parse the fetched fixture data from API
            $parsedFixtures = $this->dataParser->parseFixtures($fetchedFixtures);

            // use the Importer service to import parsed data and get status and stats of the operation
            $status[$tournament->getId()]['status'] = $this->dataImporter->importFixtures($parsedFixtures, $tournament, $this->footballApi);

            $status['total_fetched'] = $status['total_fetched'] + $status[$tournament->getId()]['status']['fixtures_fetched'];
            $status['total_added'] = $status['total_added'] + $status[$tournament->getId()]['status']['fixtures_added'];
            $status['total_updated'] = $status['total_updated'] + $status[$tournament->getId()]['status']['fixtures_updated'];
        }

        return $status;
    }
}