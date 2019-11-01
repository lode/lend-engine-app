<?php

namespace AppBundle\Controller\Admin\User;

use AppBundle\Entity\Contact;
use Doctrine\DBAL\DBALException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Postmark\PostmarkClient;
use Postmark\Models\PostmarkException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class UserEditController extends Controller
{

    /**
     * @param Request $request
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     * @Route("admin/user/{id}", name="user", defaults={"id" = 0}, requirements={"id": "\d+"})
     */
    public function userFormAction(Request $request, $id = 0)
    {
        $userManager = $this->container->get('fos_user.user_manager');

        $this->denyAccessUnlessGranted('ROLE_SUPER_USER', null, 'Unable to access this page!');

        /** @var \AppBundle\Services\TenantService $tenantService */
        $tenantService = $this->get('service.tenant');

        /** @var $user \AppBundle\Entity\Contact */
        if ($id) {
            $user = $this->getDoctrine()->getRepository('AppBundle:Contact')->find($id);
            if (!$user) {
                throw $this->createNotFoundException(
                    'No user found for id '.$id
                );
            }
            $pageTitle = 'User '.$user->getName();
            $flashMessage = 'User updated.';
        } else {
            // Creating a new user
            $pageTitle = 'Add a new user';
            $flashMessage = 'User created.';

            $user = $userManager->createUser();
            $user->setEnabled(true);
        }

        $formBuilder = $this->createFormBuilder($user,
            [
                'action' => $this->generateUrl('user', array('id' => $id))
            ]
        );

        $formBuilder->add('firstName', TextType::class, array(
            'label' => 'First name'
        ));

        $formBuilder->add('lastName', TextType::class, array(
            'label' => 'Last name'
        ));

        $formBuilder->add('email', EmailType::class, array(
            'label' => 'Email address',
            'attr' => array(
                'data-help' => ''
            )
        ));

        if ($id) {
            $formBuilder->add('autoPassword', CheckboxType::class, array(
                'label' => 'Send a new password by email',
                'mapped' => false,
                'required' => false,
                'attr' => array(
                    'data-help' => ''
                )
            ));
        } else {
            // new staff, set checkboxes on by default
            $user->addRole("ROLE_ADMIN");
            $user->addRole("ROLE_SUPER_USER");
        }

        $formBuilder->add('roles', ChoiceType::class, array(
            'choices' => array(
                'Staff member (log in to admin)' => 'ROLE_ADMIN',
                'Administrator (add items, edit settings, export)' => 'ROLE_SUPER_USER'
            ),
            'label' => 'Permissions',
            'expanded' => true,
            'multiple' => true,
            'mapped' => true,
        ));

        $formBuilder->add('enabled', CheckboxType::class, array(
            'label' => 'Active user?',
            'required' => false,
            'attr' => array(
                'data-help' => 'Un-check this to de-activate old users.'
            )
        ));

        $form = $formBuilder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $emailAddress = $form->get('email')->getData();

            $user->setUsername( $emailAddress );
            $user->setEmail( $emailAddress );
            if ($user->hasRole("ROLE_SUPER_USER") && !$user->hasRole("ROLE_ADMIN")) {
                $user->addRole("ROLE_ADMIN");
            }

            $newPassword = '';
            $sendUserEmail = false;

            if (($form->has('autoPassword') && $form->get('autoPassword')->getData() == 1) || !$id) {
                $newPassword = $this->generatePassword();
                $user->setPlainPassword($newPassword);
                $sendUserEmail = true;
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);

            try {
                $em->flush();

                if ($sendUserEmail == true) {

                    $locale = $user->getLocale();
                    $accountName = $this->get('service.tenant')->getCompanyName();

                    if (!$subject = $this->get('settings')->getSettingValue('email_welcome_subject')) {
                        $subject = $this->get('translator')->trans('le_email.login_details.subject', ['%accountName%' => $accountName], 'emails', $locale);
                    }

                    $senderName     = $tenantService->getCompanyName();
                    $replyToEmail   = $tenantService->getReplyToEmail();
                    $fromEmail      = $tenantService->getSenderEmail();
                    $postmarkApiKey = $tenantService->getSetting('postmark_api_key');

                    try {
                        $client = new PostmarkClient($postmarkApiKey);

                        // Save and switch locale for sending the email
                        $sessionLocale = $this->get('translator')->getLocale();
                        $this->get('translator')->setLocale($locale);

                        $message = $this->renderView(
                            'emails/login_details.html.twig',
                            array(
                                'email'       => $emailAddress,
                                'password'    => $newPassword
                            )
                        );

                        $toEmail = $emailAddress;
                        $client->sendEmail(
                            "{$senderName} <{$fromEmail}>",
                            $toEmail,
                            $subject,
                            $message,
                            null,
                            null,
                            true,
                            $replyToEmail
                        );

                        // Revert locale for the UI
                        $this->get('translator')->setLocale($sessionLocale);

                        $flashMessage .= " We've sent an email to " . $emailAddress . " with login information.";

                    } catch (PostmarkException $ex) {
                        $this->addFlash('error', 'Failed to send email:' . $ex->message . ' : ' . $ex->postmarkApiErrorCode);
                    } catch (\Exception $generalException) {
                        $this->addFlash('error', 'Failed to send email:' . $generalException->getMessage());
                    }
                }

                $this->addFlash('success', $flashMessage);

                return $this->redirectToRoute('users_list');

            } catch (DBALException $e) {
                $this->addFlash('debug', $e->getMessage());
            } catch (\Exception $generalException) {
                $this->addFlash('debug', $generalException->getMessage());
            }

        }

        return $this->render('modals/user.html.twig', array(
            'form' => $form->createView(),
            'user' => $user,
            'title' => $pageTitle
        ));

    }

    /**
     * @return string
     */
    private function generatePassword()
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 10; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

}