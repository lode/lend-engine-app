<?php

namespace AppBundle\Controller\MemberSite;

use AppBundle\Entity\Loan;
use AppBundle\Entity\Note;
use AppBundle\Entity\Payment;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Postmark\PostmarkClient;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class LoanExtendController extends Controller
{

    /**
     * @Route("loan/extend/{loanRowId}", name="extend_loan", requirements={"loanRowId": "\d+"})
     * @param $loanRowId
     * @param $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function extendLoan($loanRowId, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var \AppBundle\Repository\LoanRowRepository $siteRepo */
        $loanRowRepo = $this->getDoctrine()->getRepository('AppBundle:LoanRow');

        /** @var \AppBundle\Services\SettingsService $settingsService */
        $settingsService = $this->get('settings');

        /** @var \AppBundle\Repository\SiteRepository $siteRepo */
        $siteRepo = $this->getDoctrine()->getRepository('AppBundle:Site');

        /** @var \AppBundle\Services\Payment\PaymentService $paymentService */
        $paymentService = $this->get('service.payment');

        /** @var \AppBundle\Services\Contact\ContactService $contactService */
        $contactService = $this->get('service.contact');

        /** @var \AppBundle\Services\TenantService $tenantService */
        $tenantService = $this->get('service.tenant');

        $user = $this->getUser();

        if (!$newReturnDate = $request->get('new_return_date')) {
            $this->addFlash('error', "No new return date given");
            return $this->redirectToRoute('home');
        }

        if (!$newReturnTime = $request->get('new_return_time')) {
            $this->addFlash('error', "No new return time given");
            return $this->redirectToRoute('home');
        }

        if (!$newReturnSiteId = $request->get('new_return_site_id')) {
            $this->addFlash('error', "No new return site given");
            return $this->redirectToRoute('home');
        }

        /** @var $loanRow \AppBundle\Entity\LoanRow */
        if (!$loanRow = $loanRowRepo->find($loanRowId)) {
            $this->addFlash('error', "Could not find a loan row for ID {$loanRowId}");
            return $this->redirectToRoute('home');
        }

        $inventoryItem = $loanRow->getInventoryItem();

        $newDueDate = new \DateTime($newReturnDate.' '.$newReturnTime);
        $interval = $loanRow->getDueInAt()->diff($newDueDate);
        $days = round($interval->format('%R%a'), 0);

        if ($days == 1) {
            $dayWord = 'day';
        } else {
            $dayWord = 'days';
        }

        // Update the row (offsetting for timezone so that time is serialized as UTC)
        // Also done in basketController (should be anywhere we take in dates from the calendar picker)
        $tz = $settingsService->getSettingValue('org_timezone');
        $timeZone = new \DateTimeZone($tz);
        $utc = new \DateTime('now', new \DateTimeZone("UTC"));
        $offSet = -$timeZone->getOffset($utc)/3600;

        // Add a note (with local time, not UTC)
        $newDueDateLocalFormat = $newDueDate->format("d F g:i a");
        $noteText = 'Updated return date for <strong>'.$loanRow->getInventoryItem()->getName().'</strong> '.$days.' '.$dayWord.' to '.$newDueDateLocalFormat;

        $newDueDate->modify("{$offSet} hours");

        // Save
        $loanRow->setDueInAt($newDueDate);

        /** @var $newReturnSite \AppBundle\Entity\Site */
        if (!$newReturnSite = $siteRepo->find($newReturnSiteId)) {
            $this->addFlash('error', "Could not find a site for ID {$newReturnSiteId}");
            return $this->redirectToRoute('home');
        }
        $loanRow->setSiteTo($newReturnSite);

        // Update the loan
        $loan = $loanRow->getLoan();
        $loan->setReturnDate();
        $contact = $loan->getContact();

        $extensionFee = $request->get('extension_fee_amount');

        $paymentOk = true;

        if ($extensionFee != 0 && $request->get('charge_extension_fee')) {

            // Create the charge
            $payment = new Payment();
            $payment->setAmount(-$extensionFee);
            $payment->setContact($contact);
            $payment->setLoan($loan);
            $payment->setNote("Updated return date for ".$inventoryItem->getName()." {$days} {$dayWord} to ".$newDueDateLocalFormat.".");
            $payment->setCreatedBy($user);
            $payment->setInventoryItem($inventoryItem);
            $em->persist($payment);
            $noteText .= " (extension fee ".number_format($extensionFee, 2).")";

            // Mark it as paid
            $formValues = $request->get('loan_extend');

            $paymentMethodId = $formValues['paymentMethod'];
            $paymentAmount   = $formValues['paymentAmount'];
            if ($paymentMethodId && $paymentAmount) {

                /** @var \AppBundle\Entity\PaymentMethod $paymentMethod */
                $paymentMethod = $this->getDoctrine()->getRepository('AppBundle:PaymentMethod')->find($paymentMethodId);

                $stripePaymentMethodId = $this->get('settings')->getSettingValue('stripe_payment_method');

                $payment = new Payment();
                $payment->setCreatedBy($user);
                $paymentNote = Payment::TEXT_PAYMENT_RECEIVED;
                $payment->setPaymentMethod($paymentMethod);
                $payment->setAmount($extensionFee);
                $payment->setNote($paymentNote);
                $payment->setContact($loan->getContact());

                if ($stripePaymentMethodId == $paymentMethod->getId()) {
                    $payment->setPspCode($request->get('chargeId'));
                }

                if (!$paymentService->create($payment)) {
                    $paymentOk = false;
                    foreach ($paymentService->errors AS $error) {
                        $this->addFlash('error', $error);
                    }
                }

            }

        }

        if ($paymentOk == false) {
            $this->addFlash('error', "The item return date was not extended.");
            return $this->redirectToRoute('public_loan', array('loanId' => $loan->getId()));
        }

        $note = new Note();
        $note->setCreatedBy($user);
        $note->setContact($loan->getContact());
        $note->setLoan($loan);
        $note->setText($noteText);

        $em->persist($loanRow);
        $em->persist($loan);
        $em->persist($note);

        try {
            $em->flush();

            if ($extensionFee != 0) {
                $contactService->recalculateBalance($contact);
            }

            $this->addFlash('success','Loan extended OK.');
            $toEmail = $loanRow->getLoan()->getContact()->getEmail();
            $locale  = $loanRow->getLoan()->getContact()->getLocale();

            // Send an email
            if ($toEmail && $loanRow->getLoan()->getStatus() != Loan::STATUS_PENDING) {

                if (!$subject = $this->get('settings')->getSettingValue('email_loan_extension_subject')) {
                    $subject = $this->get('translator')->trans('le_email.extend.subject', [], 'emails', $locale);
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
                        'emails/loan_extend.html.twig',
                        array(
                            'loanRow'     => $loanRow
                        )
                    );

                    $client->sendEmail(
                        "{$senderName} <{$fromEmail}>",
                        $toEmail,
                        $subject,
                        $message,
                        null,
                        null,
                        null,
                        $replyToEmail
                    );

                    // Revert locale for the UI
                    $this->get('translator')->setLocale($sessionLocale);

                } catch (\Exception $generalException) {
                    $this->addFlash('error', 'Failed to send email to '.$toEmail.': ' . $generalException->getMessage());
                }
            }

        } catch (\Exception $generalException) {
            $this->addFlash('error', 'There was an error extending the loan.');
            $this->addFlash('debug', 'PaymentError: '.$generalException->getMessage());
        }

        return $this->redirectToRoute('public_loan', array('loanId' => $loan->getId()));

    }

}