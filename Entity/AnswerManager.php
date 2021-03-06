<?php

/*
 * This file is part of the BaitPollBundle package.
 *
 * (c) BAIT s.r.o. <http://www.bait.sk/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bait\PollBundle\Entity;

use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManager;
use Bait\PollBundle\Model\AnswerManager as BaseAnswerManager;
use Bait\PollBundle\Model\AnswerInterface;
use Bait\PollBundle\Model\FieldInterface;
use Doctrine\ORM\PersistentCollection;

/**
 * Doctrine 2 ORM specific answer manager.
 *
 * @author Ondrej Slintak <ondrowan@gmail.com>
 */
class AnswerManager extends BaseAnswerManager
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $repository;

    /**
     * @var string
     */
    protected $class;

    /**
     * @var string
     */
    protected $cookiePrefix;

    /**
     * @param EntityManager $entityManager
     * @param string $class
     */
    public function __construct(EntityManager $entityManager, Request $request, $class, $cookiePrefix)
    {
        $this->entityManager = $entityManager;
        $this->repository = $entityManager->getRepository($class);
        $this->request = $request;
        $this->class = $class;
        $this->cookiePrefix = $cookiePrefix;
    }

    /**
     * {@inheritDoc}
     */
    public function findBy($criteria)
    {
        return $this->repository->findBy($criteria);
    }


    /**
     * Doctrine 2 ORM specific save.
     *
     * {@inheritDoc}
     */
    public function doSave(array $answers)
    {
        foreach ($answers as $answer) {
            if (!$answer instanceof AnswerInterface) {
                throw new \InvalidArgumentException('Answer must be instance of AnswerInterface.');
            }

            $this->entityManager->persist($answer);
        }

        $this->entityManager->flush();
    }

    /**
     * {@inheritDoc}
     */
    public function doCountVotesOf(PersistentCollection $fields)
    {
        foreach ($fields as $field) {
            $fieldsCount[] = $field->getId();
            $titles[$field->getId()] = $field->getTitle();
        }

        $query = $this->entityManager->createQuery(
            sprintf("SELECT v.value, COUNT(v.id) FROM %s v WHERE v.value IN (%s) GROUP BY v.value", $this->class, implode(',', $fieldsCount))
        );

        $results = $query->getResult();

        $cleanResults = array();

        foreach ($results as $result) {
            $cleanResults[$titles[$result['value']]] = $result[1];
        }

        return $cleanResults;
    }

    /**
     * {@inheritDoc}
     */
    public function getClass()
    {
        return $this->class;
    }
}
