<?php

/*
 * This file is part of Twigony.
 *
 * © Timon F <dev@timonf.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twigony\Bundle\FrameworkBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Templating\EngineInterface;
use Twigony\Bundle\FrameworkBundle\Controller\TemplateController;

class TemplateControllerTest extends TestCase
{
    /**
     * @var TemplateController
     */
    protected $controller;

    public function setUp()
    {
        // Template Engine
        $renderMethod = function (string $name, array $parameters) : string {
            return 'Template name: ' . $name . PHP_EOL . (string) print_r($parameters, true);
        };
        $existsMethod = function (string $name) : bool {
            return $name !== '404';
        };
        $templateEngine = $this
            ->getMockBuilder(EngineInterface::class)
            ->getMock();
        $templateEngine->expects($this->any())
            ->method('render')
            ->willReturnCallback($renderMethod);
        $templateEngine->expects($this->any())
            ->method('exists')
            ->willReturnCallback($existsMethod);

        $this->controller = new TemplateController(
            $templateEngine
        );
    }

    public function testTemplateAction()
    {
        $response = $this->controller->templateAction('staticTemplate', null, [
            'foo1' => 'bar2',
        ]);

        $this->assertContains('foo1', $response->getContent());
        $this->assertContains('bar2', $response->getContent());
        $this->assertContains('Template name: staticTemplate', $response->getContent());
    }

    public function testTemplateActionWithPublicCache()
    {
        $response = $this->controller->templateAction('cacheTemplate', null, [
            'maxAge' => '250',
        ]);

        $this->assertTrue($response->isCacheable());
        $this->assertContains('max-age=250', $response->headers->get('cache-control'));
        $this->assertContains('Template name: cacheTemplate', $response->getContent());
    }

    public function testTemplateActionWithPrivateCache()
    {
        $response = $this->controller->templateAction('dontCacheTemplate', null, [
            'private' => true,
        ]);

        $this->assertFalse($response->isCacheable());
        $this->assertContains('Template name: dontCacheTemplate', $response->getContent());
    }

    public function testTemplateActionWithNotExistingTemplate()
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller->templateAction('404');
    }

    /**
     * @dataProvider getTemplateNamesDataProvider
     */
    public function testTemplateActionWithDynamicPageParameter($page)
    {
        $response = $this->controller->templateAction('views/{page}.ext', $page);

        $this->assertContains('Template name: views/' . $page . '.ext', $response->getContent());
    }

    public function getTemplateNamesDataProvider()
    {
        return [
            ['info'],
            ['about'],
            ['foo/bar'],
        ];
    }
}
