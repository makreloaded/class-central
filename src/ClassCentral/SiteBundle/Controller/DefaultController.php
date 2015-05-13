<?php

namespace ClassCentral\SiteBundle\Controller;

use ClassCentral\SiteBundle\Entity\Course;
use ClassCentral\SiteBundle\Entity\Spotlight;
use ClassCentral\SiteBundle\Entity\User;
use ClassCentral\SiteBundle\Entity\UserCourse;
use ClassCentral\SiteBundle\Form\SignupType;
use ClassCentral\SiteBundle\Services\Image;
use ClassCentral\SiteBundle\Services\Kuber;
use ClassCentral\SiteBundle\Utility\Breadcrumb;
use ClassCentral\SiteBundle\Utility\CourseUtility;
use ClassCentral\SiteBundle\Utility\PageHeader\PageHeaderFactory;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use ClassCentral\SiteBundle\Entity\Initiative;
use ClassCentral\SiteBundle\Entity\Offering;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller {
               
    public function indexAction(Request $request) {

        // Autologin if a token exists
        $this->get('user_service')->autoLogin($request);

        $cache = $this->get('Cache');
        $cl = $this->get('course_listing');
        $recent = $cl->byTime('recent',$request);
        $esCourses = $this->get('es_courses');
        $em = $this->getDoctrine()->getManager();

        $spotlights = $cache->get('spotlight_cache',function(){
           $s = $this
                ->getDoctrine()->getManager()
                ->getRepository('ClassCentralSiteBundle:Spotlight')->findAll();

            $spotlights = array();
            foreach($s as $item)
            {
                $spotlights[$item->getPosition()] = $item;
            }

            return $spotlights;
        }, array());


        // limit the results to 10 courses
        $recent['courses']['hits']['hits'] =
            array_splice($recent['courses']['hits']['hits'],0,10);

        $subjects = $cache->get('stream_list_count',
                        array( new StreamController(), 'getSubjectsList'),
                        array( $this->container )
        );

        $signupForm   = $this->createForm(new SignupType(), new User(),array(
            'action' => $this->generateUrl('signup_create_user')
        ));

        // Get a list of courses taken by the signed in user
        $uc = array();
        $ucCount = 0;
        if( $this->get('security.context')->isGranted('IS_AUTHENTICATED_FULLY') )
        {
            $user = $this->get('security.context')->getToken()->getUser();

            $qb = $em->createQueryBuilder();
            $qb
                ->add('select', 'c.id as cid')
                ->add('from','ClassCentralSiteBundle:UserCourse uc')
                ->join('uc.course', 'c')
                ->andWhere('uc.user = :userId')
                ->setParameter('userId',$user->getId() )
                ->add('orderBy', 'uc.id DESC') // newest one top
                ;
            $results = $qb->getQuery()->getArrayResult();
            $courseIds = array();
            foreach($results as $result)
            {
                $courseIds[] = $result['cid'];
            }

            $ucCount = count( $courseIds );
            if( !empty($courseIds) )
            {
                $courseIds = array_splice( $courseIds, 0 , 10);
            }
            $response = $esCourses->findByIds( $courseIds );
            $uc = $response['results'];
        }

        return $this->render('ClassCentralSiteBundle:Default:index.html.twig', array(
                'page' => 'home',
                'listTypes' => UserCourse::$lists,
                'recentCourses'   => $recent['courses'],
                'spotlights' => $spotlights,
                'spotlightMap' => Spotlight::$spotlightMap,
                'subjects' => $subjects,
                'signupForm' => $signupForm->createView(),
                'uc' => $uc,
                'ucCount' => $ucCount
               ));
    }


    public function coursesAction(Request $request, $type = 'upcoming')
    {
        // Autologin if a token exists
        $this->get('user_service')->autoLogin($request);

        if(!in_array($type, array_keys(Offering::$types))){
            // TODO: render an error page
            return false;
        }

        $cl = $this->get('course_listing');
        $data = $cl->byTime($type,$request);

        return $this->render('ClassCentralSiteBundle:Default:courses.html.twig', 
                array(
                    'offeringType' => $type,
                    'page'=>'courses',
                    'results' => $data['courses'],
                    'listTypes' => UserCourse::$lists,
                    'allSubjects' => $data['allSubjects'],
                    'allLanguages' => $data['allLanguages'],
                     'offeringTypes' => Offering::$types,
                    'sortField' => $data['sortField'],
                    'sortClass' => $data['sortClass'],
                    'pageNo' => $data['pageNo'],
                    'showHeader' => true
                ));
    }

    public function faqAction(Request $request) {
        $this->get('user_service')->autoLogin($request);
        $breadcrumbs = array(
            Breadcrumb::getBreadCrumb('FAQ', $this->container->get('router')->generate('ClassCentralSiteBundle_faq')),
        );
        return $this->render('ClassCentralSiteBundle:Default:faq.html.twig', array(
            'page' => 'faq',
            'breadcrumbs' => $breadcrumbs
        ));
    }

    public function privacyPolicyAction() {
        return $this->render('ClassCentralSiteBundle:Default:privacy.html.twig', array(
            'page' => 'privacy',
        ));
    }
    
    /**
     * 
     * Cache cant be cleared from the command line. So creating an action
     */
    public function clearCacheAction(){
        $this->get('cache')->clear();
        // Just adding a dummy page
        return $this->render('ClassCentralSiteBundle:Default:faq.html.twig', array('page' => 'faq'));
    }

    public function githubButtonAction()
    {
        if ($this->container->has('profiler'))
        {
            $this->container->get('profiler')->disable();
        }
        return $this->render('ClassCentralSiteBundle:Default:githubbtn.html.twig');
    }

    /**
     * If the token is valid it logs the user in and then redirects the user to
     * the destination url. This is done for paths that are behind Sfymony's
     * login firewall
     * @param Request $request
     */
    public function autoLoginSecureAction(Request $request)
    {
        // Autologin if a token exists
        $this->get('user_service')->autoLogin($request);

        $redirectUrl = $request->query->get('redirect_url');
        if( $redirectUrl )
        {
            return $this->redirect( $redirectUrl );
        }

    }
    
}
