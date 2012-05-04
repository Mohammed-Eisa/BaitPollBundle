<?php

/*
 * This file is part of the BaitPollBundle package.
 *
 * (c) BAIT s.r.o. <http://www.bait.sk/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bait\PollBundle;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Bait\PollBundle\FormFactory\PollFormFactoryInterface;
use Bait\PollBundle\Model\PollManagerInterface;
use Bait\PollBundle\Model\VoteManagerInterface;

/**
 * Class responsible for poll management.
 *
 * @author Ondrej Slintak <ondrowan@gmail.com>
 */
class Poll
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var EngineInterface
     */
    protected $engine;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var PollFormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var PollManagerInterface
     */
    protected $pollManager;

    /**
     * @var VoteManagerInterface
     */
    protected $voteManager;

    /**
     * @var string
     */
    protected $template;

    /**
     * @var string
     */
    protected $fieldClass;

    /**
     * @var string
     */
    protected $cookiePrefix;

    /**
     * @var string
     */
    protected $cookieDuration;

    /**
     * @var string
     */
    protected $formTheme;

    /**
     * Constructs Poll service.
     *
     * @param Request $request Current request
     * @param EngineInterface $engine Templating engine
     * @param ObjectManager $objectManager Doctrine's object manager
     * @param PollFormFactoryInterface $formFactory Poll form factory
     * @param PollManagerInterface $pollManager Poll manager
     * @param VoteManagerInterface $voteManager Vote manager
     */
    public function __construct(
        Request $request,
        EngineInterface $engine,
        ObjectManager $objectManager,
        PollFormFactoryInterface $formFactory,
        PollManagerInterface $pollManager,
        VoteManagerInterface $voteManager,
        $template,
        $fieldClass,
        $cookiePrefix,
        $cookieDuration,
        $formTheme
    )
    {
        $this->request = $request;
        $this->engine = $engine;
        $this->objectManager = $objectManager;
        $this->formFactory = $formFactory;
        $this->pollManager = $pollManager;
        $this->voteManager = $voteManager;
        $this->template = $template;
        $this->fieldClass = $fieldClass;
        $this->cookiePrefix = $cookiePrefix;
        $this->cookieDuration = $cookieDuration;
        $this->formTheme = $formTheme;
    }

    /**
     * Creates form and validates it or saves data in case some data
     * were already submitted.
     *
     * @param mixed $id Id of poll to be created
     *
     * @throws NotFoundHttpException
     */
    public function create($id, Response &$response)
    {
        $this->id = $id;

        $poll = $this->pollManager->findOneById($id);

        if (!$poll) {
            throw new NotFoundHttpException(
                sprintf("Poll with id '%s' was not found.", $id)
            );
        }

        $this->form = $this->formFactory->create($id);
        $formName = $this->form->getName();

        if ($this->request->getMethod() === 'POST' && $this->request->request->has($formName) && !$this->hasVoted()) {
            $this->form->bindRequest($this->request);

            if ($this->form->isValid()) {
                $data = $this->form->getData();

                $votes = array();

                foreach ($data as $fieldId => $value) {
                    $field = str_replace('field_', '', $fieldId);

                    $values = (array) $value;
                    $field = $this->objectManager->getReference($this->fieldClass, $field);

                    foreach ($values as $value) {
                        $vote = $this->voteManager->create($field, $value);
                        $votes[] = $vote;
                    }
                }

                try {
                    $this->voteManager->save($votes);

                    $cookie = new Cookie(sprintf('%svoted_%s', $this->cookiePrefix, $id), true, time() + $this->cookieDuration);

                    $response = new RedirectResponse($this->request->getUri());
                    $response->headers->setCookie($cookie);
                } catch (\Exception $e) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Renders poll form into given template.
     *
     * @param string $template Path to poll template
     *
     * @return string
     */
    public function render($template = null)
    {
        if (!$template) {
            $template = $this->template;
        }

        $viewData = array(
            'form' => $this->form->createView(),
            'theme' => $this->formTheme,
            'request' => $this->request,
            'alreadyVoted' => $this->hasVoted()
        );

        return $this->engine->render($template, $viewData);
    }

    /**
     * Checks if user has already voted in this poll.
     *
     * @return bool
     */
    protected function hasVoted()
    {
        if ($this->request->cookies->has(sprintf('%svoted_%s', $this->cookiePrefix, $this->id))) {
            return true;
        }

        return false;
    }
}
