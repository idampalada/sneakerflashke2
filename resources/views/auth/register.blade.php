@extends('layouts.app')

@section('title', 'Register - SneakerFlash')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-8">


        <!-- Google Register Button -->
        <div class="mb-6">
            <a href="{{ route('auth.google') }}" 
               class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-white hover:bg-gray-50 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span class="text-gray-700 font-medium">Sign up with Google</span>
            </a>
        </div>

                <!-- Apple Register Button -->
<!-- <div class="mb-6">
    <a href="{{ route('auth.apple') }}" 
       class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 rounded-lg shadow-sm bg-black hover:bg-gray-800 transition-colors focus:ring-2 focus:ring-gray-500 focus:border-gray-500">
        <svg class="w-5 h-5 mr-3 text-white" viewBox="0 0 24 24" fill="currentColor">
            <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
        </svg>
        <span class="text-white font-medium">Sign up with Apple</span>
    </a>
</div> -->

        <!-- Divider -->
        <div class="relative mb-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="px-2 bg-white text-gray-500">Or create account with email</span>
            </div>
        </div>

        <!-- Register Form -->
        <form method="POST" action="{{ route('register.submit') }}" id="registerForm">
            @csrf
            
            {{-- PERUBAHAN 1: Ganti Full Name dengan First Name + Last Name --}}
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                    <input type="text" name="first_name" id="first_name" required 
                           value="{{ old('first_name') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="First name">
                    @error('first_name')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                    <input type="text" name="last_name" id="last_name" required 
                           value="{{ old('last_name') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Last name">
                    @error('last_name')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                <input type="email" name="email" id="email" required 
                       value="{{ old('email') }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Enter your email">
                @error('email')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            {{-- PERUBAHAN 2: Tambah Phone Number --}}
            <div class="mb-4">
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                <input type="tel" name="phone" id="phone" required 
                       value="{{ old('phone') }}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="08xxxxxxxxxx">
                @error('phone')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            {{-- PERUBAHAN 3: Fix Password Toggle --}}
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <div class="relative">
                    <input type="password" name="password" id="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pr-10"
                           placeholder="Create a password">
                    <button type="button" onclick="togglePassword('password')" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye" id="passwordEye"></i>
                    </button>
                </div>
                <div class="mt-1">
                    <div class="text-xs text-gray-500">
                        Password must be at least 8 characters long
                    </div>
                </div>
                @error('password')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            
            {{-- PERUBAHAN 4: Fix Password Confirmation Toggle --}}
            <div class="mb-6">
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                <div class="relative">
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pr-10"
                           placeholder="Confirm your password">
                    <button type="button" onclick="togglePassword('password_confirmation')" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye" id="confirmEye"></i>
                    </button>
                </div>
                @error('password_confirmation')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Terms & Privacy -->
<div class="mb-6">
    <label class="flex items-start">
        <input type="checkbox" name="terms" required class="mt-1 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
        <span class="ml-2 text-sm text-gray-600">
            I agree to the 
            <a href="javascript:void(0)" onclick="openModal('termsModal')" class="text-blue-600 hover:text-blue-800">Terms of Service</a>
            and 
            <a href="javascript:void(0)" onclick="openModal('privacyModal')" class="text-blue-600 hover:text-blue-800">Privacy Policy</a>
        </span>
    </label>
    @error('terms')
        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
    @enderror
</div>
<!-- Terms of Service Modal -->
<div id="termsModal" class="modal-overlay hidden">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Terms of Service</h2>
            <button onclick="closeModal('termsModal')" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-content">
            <div class="terms-content">
                <h3>1. Acceptance of Terms</h3>
                <p>By accessing and using SneakerFlash, you accept and agree to be bound by the terms and provision of this agreement.</p>

                <h3>2. Use License</h3>
                <p>Permission is granted to temporarily download one copy of SneakerFlash materials for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title, and under this license you may not:</p>
                <ul>
                    <li>modify or copy the materials;</li>
                    <li>use the materials for any commercial purpose or for any public display;</li>
                    <li>attempt to reverse engineer any software contained on SneakerFlash website;</li>
                    <li>remove any copyright or other proprietary notations from the materials.</li>
                </ul>

                <h3>3. Account Registration</h3>
                <p>When you create an account with us, you must provide information that is accurate, complete, and current at all times. You are responsible for safeguarding the password and for maintaining the security of your account.</p>

                <h3>4. Product Information</h3>
                <p>We strive to display product colors and images as accurately as possible. However, we cannot guarantee that your device's display will accurately reflect the actual product colors.</p>

                <h3>5. Orders and Payment</h3>
                <p>All orders are subject to acceptance by SneakerFlash. We reserve the right to refuse or cancel any order for any reason. Payment must be received in full before products are shipped.</p>

                <h3>6. Shipping and Delivery</h3>
                <p>Delivery times are estimates and not guaranteed. SneakerFlash is not responsible for delays caused by shipping carriers or customs procedures.</p>

                <h3>7. Returns and Exchanges</h3>
                <p>Items may be returned within 30 days of purchase in original condition with tags attached. Custom or personalized items cannot be returned unless defective.</p>

                <h3>8. Intellectual Property</h3>
                <p>The SneakerFlash website and its original content, features, and functionality are owned by SneakerFlash and are protected by international copyright, trademark, and other intellectual property laws.</p>

                <h3>9. User Conduct</h3>
                <p>You agree not to use the service to:</p>
                <ul>
                    <li>Upload or transmit viruses or malicious code</li>
                    <li>Spam or send unsolicited messages</li>
                    <li>Violate any laws or regulations</li>
                    <li>Infringe on intellectual property rights</li>
                </ul>

                <h3>10. Limitation of Liability</h3>
                <p>SneakerFlash shall not be liable for any indirect, incidental, special, or consequential damages resulting from the use or inability to use our services.</p>

                <h3>11. Changes to Terms</h3>
                <p>We reserve the right to modify these terms at any time. Changes will be effective immediately upon posting on the website.</p>

                <h3>12. Contact Information</h3>
                <p>If you have questions about these Terms of Service, please contact us at:</p>
                <p>
                    Email: hello@sneakersflash.com<br>
                    Phone: 081313911391<br>
                    Address: West Jakarta, Indonesia
                </p>

                <p class="last-updated">Last updated: September 2025</p>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('termsModal')" class="modal-btn-secondary">Close</button>
            <button onclick="acceptTerms()" class="modal-btn-primary">I Accept</button>
        </div>
    </div>
</div>

<!-- Privacy Policy Modal -->
<div id="privacyModal" class="modal-overlay hidden">
    <div class="modal-container">
        <div class="modal-header">
            <h2 class="modal-title">Privacy Policy</h2>
            <button onclick="closeModal('privacyModal')" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-content">
            <div class="privacy-content">
                <h3>1. Information We Collect</h3>
                <p>We collect information you provide directly to us, such as when you create an account, make a purchase, or contact us.</p>
                
                <h4>Personal Information includes:</h4>
                <ul>
                    <li>Name, email address, phone number</li>
                    <li>Billing and shipping addresses</li>
                    <li>Payment information (processed securely)</li>
                    <li>Order history and preferences</li>
                    <li>Communication preferences</li>
                </ul>

                <h3>2. How We Use Your Information</h3>
                <p>We use your information to:</p>
                <ul>
                    <li>Process and fulfill your orders</li>
                    <li>Send order confirmations and updates</li>
                    <li>Provide customer support</li>
                    <li>Send promotional emails (if opted in)</li>
                    <li>Improve our products and services</li>
                    <li>Prevent fraud and enhance security</li>
                </ul>

                <h3>3. Information Sharing</h3>
                <p>We do not sell, trade, or rent your personal information to third parties. We may share your information only in these circumstances:</p>
                <ul>
                    <li>With shipping carriers to deliver your orders</li>
                    <li>With payment processors for secure transactions</li>
                    <li>To comply with legal requirements</li>
                    <li>To protect our rights and safety</li>
                </ul>

                <h3>4. Data Security</h3>
                <p>We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. This includes:</p>
                <ul>
                    <li>SSL encryption for all data transmission</li>
                    <li>Secure payment processing</li>
                    <li>Regular security audits</li>
                    <li>Limited access to personal data</li>
                </ul>

                <h3>5. Cookies and Tracking</h3>
                <p>We use cookies and similar technologies to:</p>
                <ul>
                    <li>Remember your preferences</li>
                    <li>Keep you logged in</li>
                    <li>Analyze website traffic</li>
                    <li>Provide personalized content</li>
                </ul>
                <p>You can control cookies through your browser settings.</p>

                <h3>6. Email Communications</h3>
                <p>With your consent, we may send you:</p>
                <ul>
                    <li>Order confirmations and shipping updates</li>
                    <li>Promotional offers and new product announcements</li>
                    <li>Account-related notifications</li>
                </ul>
                <p>You can unsubscribe from promotional emails at any time.</p>

                <h3>7. Data Retention</h3>
                <p>We retain your personal information for as long as necessary to provide our services, comply with legal obligations, resolve disputes, and enforce our agreements.</p>

                <h3>8. Your Rights</h3>
                <p>You have the right to:</p>
                <ul>
                    <li>Access your personal information</li>
                    <li>Correct inaccurate information</li>
                    <li>Request deletion of your data</li>
                    <li>Opt-out of marketing communications</li>
                    <li>Request data portability</li>
                </ul>

                <h3>9. Children's Privacy</h3>
                <p>Our services are not intended for children under 13. We do not knowingly collect personal information from children under 13.</p>

                <h3>10. International Transfers</h3>
                <p>Your information may be transferred to and processed in countries other than Indonesia. We ensure appropriate safeguards are in place for such transfers.</p>

                <h3>11. Changes to Privacy Policy</h3>
                <p>We may update this Privacy Policy from time to time. We will notify you of significant changes by email or through our website.</p>

                <h3>12. Contact Us</h3>
                <p>If you have questions about this Privacy Policy or your personal information, contact us at:</p>
                <p>
                    Email: privacy@sneakersflash.com<br>
                    Phone: 081313911391<br>
                    Address: West Jakarta, Indonesia
                </p>

                <p class="last-updated">Last updated: September 2025</p>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('privacyModal')" class="modal-btn-secondary">Close</button>
            <button onclick="acceptPrivacy()" class="modal-btn-primary">I Accept</button>
        </div>
    </div>
</div>

<!-- CSS Styles untuk Modal -->
<style>
/* Modal Overlay */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 10000;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.show {
    opacity: 1;
    visibility: visible;
}

/* Modal Container */
.modal-container {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    transform: scale(0.9) translateY(-20px);
    transition: all 0.3s ease;
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
}

.modal-overlay.show .modal-container {
    transform: scale(1) translateY(0);
}

/* Modal Header */
.modal-header {
    padding: 24px 24px 16px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: between;
    align-items: center;
    position: relative;
}

.modal-title {
    font-size: 24px;
    font-weight: 700;
    color: #111827;
    margin: 0;
    flex: 1;
}

.modal-close-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    background: none;
    border: none;
    font-size: 28px;
    color: #6b7280;
    cursor: pointer;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.modal-close-btn:hover {
    background: #f3f4f6;
    color: #374151;
}

/* Modal Content */
.modal-content {
    flex: 1;
    overflow-y: auto;
    padding: 0 24px;
}

.terms-content,
.privacy-content {
    padding: 16px 0;
}

.terms-content h3,
.privacy-content h3 {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin: 24px 0 12px 0;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 8px;
}

.terms-content h4,
.privacy-content h4 {
    font-size: 16px;
    font-weight: 600;
    color: #374151;
    margin: 16px 0 8px 0;
}

.terms-content p,
.privacy-content p {
    font-size: 14px;
    line-height: 1.6;
    color: #4b5563;
    margin: 0 0 12px 0;
}

.terms-content ul,
.privacy-content ul {
    margin: 8px 0 16px 0;
    padding-left: 20px;
}

.terms-content li,
.privacy-content li {
    font-size: 14px;
    line-height: 1.5;
    color: #4b5563;
    margin: 4px 0;
}

.last-updated {
    font-style: italic;
    font-weight: 600;
    color: #6b7280;
    border-top: 1px solid #e5e7eb;
    padding-top: 16px;
    margin-top: 24px !important;
}

/* Modal Footer */
.modal-footer {
    padding: 16px 24px 24px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.modal-btn-primary,
.modal-btn-secondary {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    min-width: 100px;
}

.modal-btn-primary {
    background: #2563eb;
    color: white;
}

.modal-btn-primary:hover {
    background: #1d4ed8;
}

.modal-btn-secondary {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}

.modal-btn-secondary:hover {
    background: #e5e7eb;
}

/* Responsive */
@media (max-width: 768px) {
    .modal-overlay {
        padding: 10px;
    }
    
    .modal-container {
        max-height: 95vh;
    }
    
    .modal-header {
        padding: 20px 20px 12px;
    }
    
    .modal-title {
        font-size: 20px;
        padding-right: 40px;
    }
    
    .modal-content {
        padding: 0 20px;
    }
    
    .modal-footer {
        padding: 12px 20px 20px;
        flex-direction: column;
    }
    
    .modal-btn-primary,
    .modal-btn-secondary {
        width: 100%;
        margin: 4px 0;
    }
}

/* Scrollbar untuk modal content */
.modal-content::-webkit-scrollbar {
    width: 6px;
}

.modal-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.modal-content::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.modal-content::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>


            <button type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors font-medium">
                <i class="fas fa-user-plus mr-2"></i>
                Create Account
            </button>
        </form>

        <!-- Login Link -->
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Already have an account? 
                <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-800 font-medium">Sign in</a>
            </p>
        </div>

       
@endsection

@push('scripts')
<script>
    // Open modal function
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Focus trap
        const focusableElements = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusableElements.length > 0) {
            focusableElements[0].focus();
        }
    }
}

// Close modal function
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
        
        // Restore body scroll
        document.body.style.overflow = 'auto';
    }
}

// Accept Terms function
function acceptTerms() {
    const termsCheckbox = document.querySelector('input[name="terms"]');
    if (termsCheckbox) {
        termsCheckbox.checked = true;
        
        // Trigger change event to update any validation
        const event = new Event('change', { bubbles: true });
        termsCheckbox.dispatchEvent(event);
    }
    
    closeModal('termsModal');
    
    // Show confirmation
    showNotification('Terms of Service accepted successfully!', 'success');
}

// Accept Privacy function  
function acceptPrivacy() {
    const privacyCheckbox = document.querySelector('input[name="privacy_accepted"]') || 
                           document.querySelector('input[name="terms"]'); // Fallback untuk register form
    
    if (privacyCheckbox) {
        privacyCheckbox.checked = true;
        
        // Trigger change event to update any validation
        const event = new Event('change', { bubbles: true });
        privacyCheckbox.dispatchEvent(event);
    }
    
    closeModal('privacyModal');
    
    // Show confirmation
    showNotification('Privacy Policy accepted successfully!', 'success');
}

// Show notification function
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.modal-notification');
    existingNotifications.forEach(notif => notif.remove());
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `modal-notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform translate-x-full`;
    
    // Set background color based on type
    switch (type) {
        case 'success':
            notification.classList.add('bg-green-500');
            break;
        case 'error':
            notification.classList.add('bg-red-500');
            break;
        default:
            notification.classList.add('bg-blue-500');
    }
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        const modalId = e.target.id;
        closeModal(modalId);
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const visibleModal = document.querySelector('.modal-overlay.show');
        if (visibleModal) {
            closeModal(visibleModal.id);
        }
    }
});
// Fixed Password Toggle Function
function togglePassword(fieldId) {
    console.log('togglePassword called with:', fieldId); // Debug log
    
    const passwordInput = document.getElementById(fieldId);
    const eyeIcon = document.getElementById(fieldId === 'password' ? 'passwordEye' : 'confirmEye');
    
    console.log('Elements found:', {
        passwordInput: !!passwordInput,
        eyeIcon: !!eyeIcon,
        eyeIconId: fieldId === 'password' ? 'passwordEye' : 'confirmEye'
    }); // Debug log
    
    if (passwordInput && eyeIcon) {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');
            console.log('Password shown'); // Debug log
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.remove('fa-eye-slash');
            eyeIcon.classList.add('fa-eye');
            console.log('Password hidden'); // Debug log
        }
    } else {
        console.error('Password toggle error: Elements not found');
    }
}

// Wait for DOM to load before adding event listeners
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded'); // Debug log
    
    // Password confirmation validation
    const passwordConfirmInput = document.getElementById('password_confirmation');
    if (passwordConfirmInput) {
        passwordConfirmInput.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.classList.add('border-red-500');
            } else {
                this.setCustomValidity('');
                this.classList.remove('border-red-500');
            }
        });
    }

    // Auto-focus on first name input
    const firstNameInput = document.getElementById('first_name');
    if (firstNameInput) {
        firstNameInput.focus();
    }
    
    // Test if toggle buttons work
    const toggleButtons = document.querySelectorAll('button[onclick*="togglePassword"]');
    console.log('Toggle buttons found:', toggleButtons.length);
});
</script>
@endpush
