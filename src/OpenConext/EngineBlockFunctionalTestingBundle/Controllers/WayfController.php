<?php

/**
 * Copyright 2010 SURFnet B.V.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace OpenConext\EngineBlockFunctionalTestingBundle\Controllers;

use OpenConext\EngineBlockFunctionalTestingBundle\Helper\TestEntitySeeder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * @package OpenConext\EngineBlockFunctionalTestingBundle\Controllers
 * @SuppressWarnings("PMD")
 */
class WayfController extends AbstractController
{
    private $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function wayfAction(Request $request)
    {
        $currentLocale = $request->get('lang', 'en');
        $request->cookies->set('lang', $currentLocale);

        // Extract and validate boolean parameters
        $backLink = filter_var($request->get('backLink', false), FILTER_VALIDATE_BOOLEAN);
        $displayUnconnectedIdpsWayf = filter_var($request->get('displayUnconnectedIdpsWayf', false), FILTER_VALIDATE_BOOLEAN);
        $addDiscoveries = filter_var($request->get('addDiscoveries', true), FILTER_VALIDATE_BOOLEAN);
        $rememberChoiceFeature = filter_var($request->get('rememberChoiceFeature', false), FILTER_VALIDATE_BOOLEAN);
        $showIdPBanner = filter_var($request->get('showIdPBanner', true), FILTER_VALIDATE_BOOLEAN);

        // Extract numeric parameters
        $cutoffPointForShowingUnfilteredIdps = (int) $request->get('cutoffPointForShowingUnfilteredIdps', 100);
        $connectedIdps = (int) $request->get('connectedIdps', 5);
        $unconnectedIdps = (int) $request->get('unconnectedIdps', 0);
        $randomIdps = (int) $request->get('randomIdps', 0);
        $defaultIdpEntityId = $request->get('defaultIdpEntityId', null);

        // Build IDP list based on configuration
        if ($randomIdps === 0) {
            $idpList = TestEntitySeeder::buildIdps($connectedIdps, $unconnectedIdps, $currentLocale, $defaultIdpEntityId, $addDiscoveries);
        } else {
            $idpList = TestEntitySeeder::buildRandomIdps($randomIdps, $currentLocale, $defaultIdpEntityId);
        }

        return new Response($this->twig->render(
            '@theme/Authentication/View/Proxy/wayf.html.twig',
            [
                'action' => $this->generateUrl('functional_testing_handle_wayf'),
                'greenHeader' => $currentLocale,
                'helpLink' => '/authentication/idp/help-discover?lang='.$currentLocale,
                'backLink' => $backLink,
                'cutoffPointForShowingUnfilteredIdps' => $cutoffPointForShowingUnfilteredIdps,
                'showIdPBanner' => $showIdPBanner,
                'rememberChoiceFeature' => $rememberChoiceFeature,
                'showRequestAccess' => $displayUnconnectedIdpsWayf,
                'requestId' => 'bogus-request-id',
                'serviceProvider' => TestEntitySeeder::buildSp(),
                'idpList' => $idpList,
                'showRequestAccessContainer' => true,
            ]
        ));
    }

    public function handleWayfAction(Request $request)
    {
        if ($request->request->has('idp')) {
            return $this->redirectToRoute(
                'open_conext_engine_block_authentication_homepage',
                [
                    'idp' => $request->request->get('idp')
                ]
            );
        }
        throw new AccessDeniedException('No IdP parameter found');
    }
}
