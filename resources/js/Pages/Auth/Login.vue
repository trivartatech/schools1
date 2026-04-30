<template>
    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <Head title="Login" />
        <div class="mx-auto w-full max-w-md">
            <!-- App Logo -->
            <div class="flex justify-center items-center gap-3">
                <div class="w-10 h-10 bg-indigo-600 rounded-lg shadow-sm flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                </div>
                <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                    School ERP
                </h2>
            </div>
            <p class="mt-2 text-center text-sm text-gray-600">
                Sign in to your account
            </p>
        </div>

        <div class="mt-8 mx-auto w-full max-w-md">
            <div class="bg-white py-8 px-4 shadow-sm sm:rounded-xl sm:px-10 border border-gray-100">
                
                <!-- Email/Password Flow -->
                <div v-show="loginMethod === 'email'">
                    <form class="space-y-6" @submit.prevent="submitEmail">
                        <!-- Error Alert -->
                        <div v-if="emailForm.errors.email" class="rounded-md bg-red-50 p-4 border border-red-100">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">{{ emailForm.errors.email }}</h3>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700"> Login Identity </label>
                            <div class="mt-1">
                                <input id="email" v-model="emailForm.email" type="text" required class="appearance-none block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Email, Username, or Mobile Number..." />
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700"> Password </label>
                            <div class="mt-1 relative">
                                <input id="password" v-model="emailForm.password" :type="showPassword ? 'text' : 'password'" required class="appearance-none block w-full px-3 py-2.5 pr-10 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="••••••••" />
                                <button type="button" @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-indigo-600 focus:outline-none"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    :title="showPassword ? 'Hide password' : 'Show password'"
                                    tabindex="-1">
                                    <svg v-if="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input id="remember-me" v-model="emailForm.remember" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded cursor-pointer" />
                                <label for="remember-me" class="ml-2 block text-sm text-gray-900 cursor-pointer"> Remember me </label>
                            </div>

                            <div class="text-sm">
                                <a href="#" class="font-medium text-indigo-600 hover:text-indigo-500 transition duration-150 ease-in-out"> Forgot your password? </a>
                            </div>
                        </div>

                        <div>
                            <Button type="submit" :loading="emailForm.processing" block>
                                {{ emailForm.processing ? 'Signing in...' : 'Sign in' }}
                            </Button>
                        </div>
                    </form>

                    <div class="mt-6">
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-200"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-2 bg-white text-gray-500"> Or sign in with </span>
                            </div>
                        </div>

                        <div class="mt-6">
                            <Button variant="secondary" @click="loginMethod = 'otp'" type="button" block>
                                <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                Login with Mobile OTP
                            </Button>
                        </div>

                        <div class="mt-3">
                            <button type="button" @click="quickDemoLogin" :disabled="emailForm.processing"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-dashed border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 text-sm font-medium rounded-lg transition disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Quick Demo Login
                            </button>
                            <p class="mt-1.5 text-center text-xs text-gray-400">
                                For demo only — signs in as principal@trivarta.in
                            </p>
                        </div>
                    </div>
                </div>

                <!-- OTP Flow -->
                <div v-show="loginMethod === 'otp'">
                    <!-- Step 1: Request OTP -->
                    <form v-if="!otpSent" @submit.prevent="requestOtp" class="space-y-6">
                        <div v-if="otpForm.errors.phone" class="rounded-md bg-red-50 p-4 border border-red-100">
                            <h3 class="text-sm font-medium text-red-800">{{ otpForm.errors.phone }}</h3>
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700"> Mobile Number </label>
                            <div class="mt-1 flex rounded-md shadow-sm">
                                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                                    +91
                                </span>
                                <input id="phone" v-model="otpForm.phone" type="tel" required class="flex-1 min-w-0 block w-full px-3 py-2.5 rounded-none rounded-r-lg focus:ring-indigo-500 border-gray-300 sm:text-sm" placeholder="9876543210" />
                            </div>
                        </div>

                        <div>
                            <Button type="submit" :loading="otpForm.processing" block>
                                {{ otpForm.processing ? 'Sending...' : 'Send OTP via SMS' }}
                            </Button>
                        </div>
                    </form>

                    <!-- Step 2: Verify OTP -->
                    <form v-else @submit.prevent="verifyOtp" class="space-y-6">
                        <div v-if="verifyForm.errors.otp" class="rounded-md bg-red-50 p-4 border border-red-100">
                            <h3 class="text-sm font-medium text-red-800">{{ verifyForm.errors.otp }}</h3>
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label for="otp" class="block text-sm font-medium text-gray-700"> Enter 6-digit OTP </label>
                                <span class="text-xs text-gray-500">Sent to {{ otpForm.phone }}</span>
                            </div>
                            <div class="mt-1">
                                <input id="otp" v-model="verifyForm.otp" type="text" maxlength="6" required class="appearance-none block w-full px-3 py-2.5 text-center tracking-widest text-lg font-mono border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="------" />
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input id="otp-remember" v-model="verifyForm.remember" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded cursor-pointer" />
                                <label for="otp-remember" class="ml-2 block text-sm text-gray-900 cursor-pointer"> Remember me </label>
                            </div>
                        </div>

                        <div>
                            <Button variant="success" type="submit" :disabled="verifyForm.processing || verifyForm.otp.length !== 6" block>
                                {{ verifyForm.processing ? 'Verifying...' : 'Verify & Login' }}
                            </Button>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="button" @click="requestOtp" :disabled="otpForm.processing" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 transition">
                                Resend OTP
                            </button>
                        </div>
                    </form>

                    <div class="mt-6">
                        <Button variant="secondary" @click="resetToEmail" type="button" block>
                            <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            Back to Email Login
                        </Button>
                    </div>
                </div>

            </div>
            
            <p class="mt-6 text-center text-xs text-gray-500">
                &copy; {{ new Date().getFullYear() }} Educational Trust. All rights reserved.
            </p>
        </div>

        <Toast />
    </div>
</template>

<script setup>
import { ref, watchEffect } from 'vue'
import { useForm, usePage, Head } from '@inertiajs/vue3'
import Button from '@/Components/ui/Button.vue';
import Toast from '@/Components/ui/Toast.vue';
import { useToast } from '@/Composables/useToast';

const toast = useToast();

watchEffect(() => {
    const f = usePage().props.flash;
    if (f?.status) toast.info(f.status);
    if (f?.success) toast.success(f.success);
    if (f?.error) toast.error(f.error);
});

const loginMethod = ref('email') // 'email' or 'otp'
const otpSent = ref(false)
const showPassword = ref(false)

// Email Auth Form
const emailForm = useForm({
    email: '',
    password: '',
    remember: false,
})

const submitEmail = () => {
    emailForm.post('/login', {
        onFinish: () => emailForm.reset('password'),
    })
}

const quickDemoLogin = () => {
    emailForm.email = 'principal@trivarta.in'
    emailForm.password = 'password'
    emailForm.remember = false
    submitEmail()
}

// OTP Request Form
const otpForm = useForm({
    phone: '',
})

const requestOtp = () => {
    otpForm.post('/login/otp/request', {
        preserveScroll: true,
        onSuccess: () => {
            otpSent.value = true;
            verifyForm.phone = otpForm.phone; // copy phone over
            verifyForm.otp = ''; // reset OTP field if resending
        }
    })
}

// OTP Verify Form
const verifyForm = useForm({
    phone: '',
    otp: '',
    remember: false
})

const verifyOtp = () => {
    verifyForm.post('/login/otp/verify', {
        onSuccess: () => {
            // successful login will redirect via inertia
        }
    })
}

const resetToEmail = () => {
    loginMethod.value = 'email';
    otpSent.value = false;
    otpForm.reset();
    verifyForm.reset();
    emailForm.clearErrors();
    otpForm.clearErrors();
    verifyForm.clearErrors();
}
</script>
