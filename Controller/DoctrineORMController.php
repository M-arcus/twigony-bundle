<?php

namespace Twigony\Bundle\FrameworkBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Templating\EngineInterface;
use Twigony\Bundle\FrameworkBundle\Form\AutomaticFormBuilder;

/**
 * Twigony's Template Controller for Doctrine ORM Entities
 *
 * All controller actions can be used in the router definition without having any own controller.
 *
 * @author Timon F <dev@timonf.de>
 */
class DoctrineORMController
{
    use CacheTrait;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var AutomaticFormBuilder
     */
    private $automaticFormBuilder;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var EngineInterface
     */
    private $templateEngine;

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(
        AutomaticFormBuilder $automaticFormBuilder,
        EngineInterface $templateEngine,
        RouterInterface $router,
        FormFactoryInterface $formFactory,
        Session $session,
        EntityManagerInterface $entityManager
    ) {
        $this->automaticFormBuilder = $automaticFormBuilder;
        $this->templateEngine = $templateEngine;
        $this->router = $router;
        $this->formFactory = $formFactory;
        $this->session = $session;
        $this->entityManager = $entityManager;
    }

    /**
     * Displays a single entity.
     *
     * <code># routing.yml
     *   show_post:
     *     path: '/post/{id}' # Make sure, you are using "id" as id parameter!
     *     defaults:
     *       _controller: 'TwigonyFrameworkBundle:Default:view'
     *       template: 'post/show.html.twig'
     *       entity:   'AppBundle\Entity\Post'
     *       options:
     *         as: 'post' # Using this option you can use it like {{ post.id }} in your template.
     * </code>
     *
     * @param Request $request
     * @param string  $template Template path and file name
     * @param string  $entity   Full class name of entity to list
     * @param array   $options  Additional configuration options. Following options are possible:
     *                          - "as" (Default: "entity") -> can change the key of the result entity.
     * @return Response
     */
    public function viewAction(Request $request, $template, $entity, $options = []) : Response
    {
        $repository = $this->entityManager->getRepository($entity);
        $id         = $request->query->get('id');

        $entityKey = array_key_exists('as', $options) ? $options['as'] : 'entity';
        $findOneBy = ['id' => $id];
        $entity    = $repository->findOneBy($findOneBy);

        if (null === $entity) {
            throw new NotFoundHttpException(sprintf(
                'Entity "%s" with id "%s" not found.',
                $entity,
                (string) $id
            ));
        }

        $response = new Response($this->templateEngine->render($template, [
            'options' => $options,
            $entityKey => $entity,
        ]));

        $this->applyCacheOptions($response, $options);

        return $response;
    }

    /**
     * List all entries of an entity.
     *
     * <code># routing.yml
     *   blog_posts:
     *     path: '/'
     *     defaults:
     *       _controller: 'TwigonyFrameworkBundle:Default:list'
     *       template: 'post/index.html.twig'
     *       entity:   'AppBundle\Entity\Post'
     *       options:
     *         as: 'posts' # So you can use it like {% for post in posts %} in your template.
     * </code>
     *
     * @param Request $request
     * @param string  $template Template path and file name
     * @param string  $entity   Full class name of entity to list
     * @param array   $options  Additional configuration options. Following options are possible:
     *                          - "as" (Default: "entities") -> can change the key of the result entities.
     * @return Response
     */
    public function listAction(Request $request, $template, $entity, $options) : Response
    {
        $repository = $this->entityManager->getRepository($entity);

        // todo: Adding a configurable paginator later.
        $entities = $repository->findAll();

        $entitiesKey = array_key_exists('as', $options) ? $options['as'] : 'entities';

        $response = new Response($this->templateEngine->render($template, [
            'options' => $options,
            $entitiesKey => $entities,
        ]));

        $this->applyCacheOptions($response, $options);

        return $response;
    }

    /**
     * Displays a form for an entity and can save them to database after submitting and validating.
     *
     * <code># routing.yml
     *   contact:
     *     path: '/post/{id}/edit'
     *     defaults:
     *       _controller: 'TwigonyFrameworkBundle:Default:edit'
     *       template: 'post/show.html.twig'
     *       entity:   'AppBundle\Entity\Comment'
     *       options:
     *         form_class: 'AppBundle/Form/CommentType' # If you want a custom form
     *         flash: 'Comment posted successfully! :)' # Flash message for "notice" bag
     *         redirect: 'homepage' # Redirect after save was successful
     * </code>
     *
     * @param Request $request
     * @param string  $template Template path and file name
     * @param string  $entity   Full class name of entity to list
     * @param array   $options  Additional configuration options. Following options are possible:
     *                          - "form_class" (optional) -> Custom form. Otherwise form will be created for you
     *                          - "flash" (optional) -> Flash bag message on success (will be added as "notice")
     *                          - "redirect" (optional) -> Route to redirect after success
     * @return Response
     */
    public function editAction(Request $request, $template, $entity, $options) : Response
    {
        $repository = $this->entityManager->getRepository($entity);
        $id = $request->query->get('id');
        $findOneBy = ['id' => $id];
        $entity = $repository->findOneBy($findOneBy);

        if (null === $entity) {
            throw new NotFoundHttpException(sprintf(
                'Entity "%s" with id "%s" not found.',
                $entity,
                (string) $id
            ));
        }

        if (array_key_exists('form_class', $options)) {
            $form = $this->formFactory->create($options['form_class'], $entity);
        } else {
            $form = $this->automaticFormBuilder->buildFormByClass($entity);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($form->getData());
            $this->entityManager->flush();

            if (array_key_exists('flash', $options)) {
                $this->session->getFlashBag()->add('notice', $options['flash']);
            }

            if (array_key_exists('redirect', $options)) {
                return new RedirectResponse($this->router->generate($options['redirect']), 301);
            }
        }

        $response = new Response($this->templateEngine->render($template, [
            'options' => $options,
            'form' => $form->createView(),
        ]));

        $this->applyCacheOptions($response, $options);

        return $response;
    }
}
