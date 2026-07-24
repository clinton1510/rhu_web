/**
 * reCAPTCHA Configuration for RedPulse
 * 
 * To set up reCAPTCHA:
 * 1. Go to https://www.google.com/recaptcha/admin
 * 2. Register your site and get your site key
 * 3. Choose reCAPTCHA v2 with "I'm not a robot" checkbox
 * 4. Add your domain (for development, you can add 'localhost')
 * 5. Replace the placeholder site key below with your actual site key
 * 
 * Note: For testing purposes during development, you can use Google's test keys:
 * Test Site Key: 6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI
 * This test key will always pass validation in the client, but should NOT be used in production.
 * 
 * SECURITY NOTE: The site key is safe to expose in client-side code.
 * The secret key should NEVER be included in frontend code and must be kept on your backend server.
 */

export const RECAPTCHA_SITE_KEY = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI'; // Test key - Replace with your actual key

/**
 * For production deployment, replace the test key above with your actual reCAPTCHA site key.
 * Example: export const RECAPTCHA_SITE_KEY = '6LcYourActualSiteKey-XXXXXXXXXXX';
 */
