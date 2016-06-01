<?php

namespace Devlabs\SportifyBundle\Entity;

/**
 * Class TournamentRepository
 * @package Devlabs\SportifyBundle\Entity
 */
class TournamentRepository extends \Doctrine\ORM\EntityRepository
{
    public function getJoined()
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('t')
            ->from('DevlabsSportifyBundle:Tournament', 't')
//            ->where('t.id = :tournament_id')
//            ->setParameters(array('tournament_id' => 1))
            ->getQuery()
            ->getResult();
    }

    public function getNotJoined()
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('t')
            ->from('DevlabsSportifyBundle:Tournament', 't')
//            ->where('t.id = :tournament_id')
//            ->setParameters(array('tournament_id' => 1))
            ->getQuery()
            ->getResult();
    }
}
