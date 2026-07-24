# reCAPTCHA Setup Guide for RedPulse

## Overview
All sign-in pages (Donor, Hospital, and Admin) now include Google reCAPTCHA v2 for enhanced security against bots and automated attacks.

## Current Status
✅ **reCAPTCHA is currently configured with Google's test key** for development purposes.

## Testing the Implementation
The current test key (`6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI`) will:
- Display the reCAPTCHA checkbox on all login pages
- Always pass validation (for testing purposes)
- Show "localhost" as the domain in the reCAPTCHA badge

**Important**: This test key should NEVER be used in production!

## Setting Up Your Own reCAPTCHA

### Step 1: Register Your Site
1. Go to https://www.google.com/recaptcha/admin
2. Sign in with your Google account
3. Click **"+"** to register a new site

### Step 2: Configure Your Site
- **Label**: RedPulse Blood Donation System (or your preferred name)
- **reCAPTCHA type**: Choose **reCAPTCHA v2** → **"I'm not a robot" Checkbox**
- **Domains**: 
  - For development: Add `localhost`
  - For production: Add your actual domain (e.g., `redpulse.ph`, `www.redpulse.ph`)
- **Owners**: Add email addresses of team members who should have access
- Accept the reCAPTCHA Terms of Service
- Click **Submit**

### Step 3: Get Your Keys
After registering, you'll receive:
- **Site Key** (Safe to expose in client-side code)
- **Secret Key** (MUST be kept secure on your backend server)

### Step 4: Update the Configuration
1. Open `/src/config/recaptcha.ts`
2. Replace the test key with your actual **Site Key**:
   ```typescript
   export const RECAPTCHA_SITE_KEY = 'YOUR_ACTUAL_SITE_KEY_HERE';
   ```

### Step 5: Backend Verification (Production)
For production deployment, you **MUST** verify the reCAPTCHA token on your backend server:

1. When the user submits the form, send the captcha token to your backend
2. Your backend should verify the token with Google's API:
   ```
   POST https://www.google.com/recaptcha/api/siteverify
   ```
3. Include these parameters:
   - `secret`: Your secret key (never expose this!)
   - `response`: The captcha token from the frontend
   - `remoteip`: (Optional) The user's IP address

4. Check the response to confirm the user is human before allowing login

## Security Features

### What reCAPTCHA Protects Against:
✅ Automated bot attacks  
✅ Credential stuffing attempts  
✅ Brute force login attempts  
✅ Spam and abuse  

### Compliance with Philippine Regulations:
- **RA 7719 (National Blood Services Act)**: Protects the integrity of the blood donation system
- **Data Privacy Act of 2012**: reCAPTCHA processes minimal user data and complies with Google's privacy policy

## Pages with reCAPTCHA

All three login pages now include reCAPTCHA:
- ✅ **Donor Login** (`/donor/login`)
- ✅ **Hospital Login** (`/hospital/login`)
- ✅ **Admin Login** (`/admin/login`)

## User Experience

Users will see:
1. Standard login form with email and password
2. reCAPTCHA checkbox below the form fields
3. Users must click "I'm not a robot" before signing in
4. Submit button will only work after completing the reCAPTCHA

## Troubleshooting

### reCAPTCHA not showing?
- Check your internet connection (reCAPTCHA requires loading Google's scripts)
- Verify the site key is correct in `/src/config/recaptcha.ts`
- Check browser console for errors

### "Invalid domain" error?
- Add your current domain to the reCAPTCHA admin console
- For local development, make sure `localhost` is added

### Always getting validation errors?
- If using the test key, it should always pass
- If using your own key, verify it's the **Site Key**, not the Secret Key
- Check that backend verification is working correctly

## Resources

- [Google reCAPTCHA Documentation](https://developers.google.com/recaptcha/docs/display)
- [reCAPTCHA Admin Console](https://www.google.com/recaptcha/admin)
- [reCAPTCHA Best Practices](https://developers.google.com/recaptcha/docs/faq)

## For Production Deployment

Before going live, ensure you:
1. ✅ Replace the test key with your production Site Key
2. ✅ Implement backend verification of the captcha token
3. ✅ Add your production domain to the reCAPTCHA admin console
4. ✅ Never expose your Secret Key in client-side code
5. ✅ Test thoroughly on your production domain

---

**Built for RedPulse - IT3201-BA Capstone Project**  
*Securing blood donation management with AI and advanced protection*
