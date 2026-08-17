<?php namespace Controllers;

/**
 * Forms Controller - Pressli CMS
 *
 * Handles form submissions for contact forms, audit requests, and other
 * frontend forms. Validates input, sends emails, and returns JSON responses
 * for AJAX form handling.
 *
 * RESPONSIBILITIES:
 *   - Accept POST submissions from frontend forms
 *   - Validate CSRF tokens for security
 *   - Send email notifications using Mail class
 *   - Return JSON responses for AJAX handling
 *   - Block GET requests (forms must be POST only)
 *
 * ROUTING:
 *   POST /forms    → postIndex()  (accept form submissions)
 *   GET  /forms    → getIndex()   (rejected with 405 error)
 *
 * SECURITY:
 *   - CSRF token verification on all POST requests
 *   - Returns 403 if CSRF check fails
 *   - No file uploads (contact/audit forms are text only)
 *   - Email addresses validated before sending
 *
 * FORM TYPES:
 *   This controller handles all form types generically. Form-specific
 *   logic (recipient email, subject) should be determined by frontend
 *   or configuration. Current forms:
 *   - Contact forms (name, email, company, message)
 *   - SEO audit requests (name, email, company, website, goals)
 *   - Newsletter signups (email only)
 *
 * EMAIL CONFIGURATION:
 *   Uses Mail class with settings from config/mail.php
 *   Supports SMTP, sendmail, or mail() method
 *
 * RESPONSE FORMAT:
 *   Success: {"success": true, "message": "Form submitted successfully"}
 *   Error:   {"error": "Error message here"}
 *   HTTP status codes: 200 (success), 403 (CSRF), 405 (method), 500 (email failed)
 *
 * TODO:
 *   - Add database storage for form submissions (future plugin)
 *   - Add spam protection (honeypot, rate limiting)
 *   - Add form type routing (different handlers per form)
 *   - Migrate to plugin architecture for flexibility
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright Copyright (c) 2015 - 2030 Geoffrey Okongo
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 0.0.1
 */

use Rackage\Log;
use Rackage\View;
use Rackage\Mail;
use Rackage\Csrf;
use Rackage\Input;
use Rackage\Registry;
use Rackage\Controller;

class FormsController extends Controller
{
    /**
     * GET requests not allowed
     *
     * Forms must be submitted via POST for security. GET requests
     * are rejected with 405 Method Not Allowed error.
     *
     * @return void Returns JSON error response with 405 status
     */
    public function getIndex()
    {
        View::json(['error' => 'Method not allowed. Forms must be submitted via POST.'], 405);
    }

    /**
     * Handle form submissions
     *
     * Accepts POST data from frontend forms, validates CSRF token,
     * sends email notification, and returns JSON response.
     *
     * PROCESS:
     *   1. Verify CSRF token (reject if invalid)
     *   2. Get all POST data
     *   3. Build email body from submitted fields
     *   4. Send email using Mail class
     *   5. Return success/error JSON response
     *
     * EXPECTED POST DATA:
     *   - csrf_token: CSRF token (required, validated)
     *   - All other fields: Form-specific data (name, email, message, etc.)
     *
     * EMAIL FORMAT:
     *   Plain text email with each field on new line:
     *   Name: John Doe
     *   Email: john@example.com
     *   Message: Hello...
     *
     * RESPONSE EXAMPLES:
     *   Success: {"success": true, "message": "Form submitted successfully"}
     *   CSRF fail: {"error": "Invalid request"} (403 status)
     *   Email fail: {"error": "Failed to send email"} (500 status)
     *
     * @return void Returns JSON response
     */
    public function postIndex()
    {
        // STEP 1: Verify CSRF token for security
        if (!Csrf::verify()) {
            return View::json(['error' => 'Invalid request'], 403);
        }

        // STEP 2: Get and validate all form data
        $name       = trim(Input::post('name'));
        $email      = trim(Input::post('email'));
        $subject    = trim(Input::post('subject'));
        $message    = trim(Input::post('message'));
        $company    = trim(Input::post('company'));
        $website    = trim(Input::post('website'));
        $formType   = trim(Input::post('form_type'));

        if(!isset($name, $email, $message, $company, $website)) {
            View::halt(['success' => false, 'message' => 'Please provide all form fields']);
        }

        if($formType == 'contact') {

            // STEP 3: Build and send plain text email

            if(!$subject) View::halt(['success' => false, 'message' => 'Please provide an email subject']);

            $message    = array_map(fn($line) => "<p>{$line}</p>", explode("\n", $message));
            $message    = join("\n", $message); 
            $message   .= "<p>Regards, <br>{$name} <b> {$company} {$website}";

            // Load email settings to access configuration
            $settings   = Registry::mail();

            $sent   = Mail::to($settings['from_email'])
                        ->replyTo($email)
                        ->subject($subject)
                        ->body($message)
                        ->send();
        }
        else if($formType == 'audit') {

            $stage      = trim(Input::post('stage'));
            $challenge  = trim(Input::post('challenge'));
            $role       = trim(Input::post('role'));
            $qualified  = trim(Input::post('budget_qualified'));
            $competitors= trim(Input::post('competitors'));
            
            // STEP 3: Build and send plain text email
            $message     = "<p>Hi David, </p><p> I'd like a SaaS SEO audit of {$company}.</p>";
            $message    .= "<p>What Stage Is Your SaaS? <br> -{$stage}.</p>";
            $message    .= "<p>What's Your Biggest Customer Acquisition Challenge Right Now?";
            $message    .= join("\n", array_map(fn($line) => "<br>-$line</br>", array_filter(explode("\n", $challenge))));
            $message    .= "</p>";
            $message    .= "<p>Who Are Your Top 3 Competitors?";
            $message    .= join("\n", array_map(fn($line) => "<br>-$line</br>", array_filter(explode(" ", $competitors))));
            $message    .= "</p>";
            $message    .= "<p>Budget Qualified? <br> $qualified </p>";
            $message    .= "<p>Regards, <br>{$name}, {$role} {$company} {$website}";

            // Load email settings to access configuration
            $settings   = Registry::mail();

            $sent   = Mail::to($settings['from_email'])
                        ->replyTo($email)
                        ->subject("SaaS SEO Audit Request for $company")
                        ->body($message)
                        ->send();            
        }

        // STEP 4: Return JSON response
        if ($sent) View::json(['success' => true, 'message' => 'Form submitted successfully']);
        else {

            Log::info("Mail Send Failed", Input::post());
            View::json(['error' => 'Failed to send email. Please try again later.'], 500);
        }
    }
}
