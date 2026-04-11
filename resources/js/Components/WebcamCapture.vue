<script setup>
import { ref, onUnmounted } from 'vue';
import Button from '@/Components/ui/Button.vue';

const emit = defineEmits(['captured', 'close']);
const props = defineProps({ title: { type: String, default: 'Capture Photo' } });

const videoRef = ref(null);
const canvasRef = ref(null);
const capturedImage = ref(null);
const stream = ref(null);
const cameraActive = ref(false);
const error = ref('');

async function startCamera() {
    error.value = '';
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false });
        videoRef.value.srcObject = stream.value;
        cameraActive.value = true;
        capturedImage.value = null;
    } catch (e) {
        error.value = 'Camera access denied or unavailable. Please allow camera permission.';
    }
}

function stopCamera() {
    if (stream.value) {
        stream.value.getTracks().forEach(t => t.stop());
        stream.value = null;
        cameraActive.value = false;
    }
}

function capture() {
    const canvas = canvasRef.value;
    const video = videoRef.value;
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    capturedImage.value = canvas.toDataURL('image/jpeg', 0.85);
    stopCamera();
}

function retake() {
    capturedImage.value = null;
    startCamera();
}

function confirmCapture() {
    emit('captured', capturedImage.value);
    stopCamera();
}

function cancel() {
    stopCamera();
    emit('close');
}

onUnmounted(() => stopCamera());
</script>

<template>
    <div class="fixed inset-0 bg-black/70 z-[60] flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="font-bold text-gray-900">{{ title }}</h3>
                <button @click="cancel" class="text-gray-400 hover:text-gray-800 text-xl">&times;</button>
            </div>
            <div class="p-4 space-y-4">
                <!-- Error -->
                <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ error }}</div>

                <!-- Camera view -->
                <div class="relative bg-gray-900 rounded-lg overflow-hidden aspect-video flex items-center justify-center">
                    <video v-show="cameraActive && !capturedImage" ref="videoRef" autoplay playsinline class="w-full h-full object-cover"></video>
                    <img v-if="capturedImage" :src="capturedImage" class="w-full h-full object-cover">
                    <div v-if="!cameraActive && !capturedImage" class="text-center text-gray-400 p-8">
                        <div class="text-5xl mb-3">📷</div>
                        <p class="text-sm">Click "Open Camera" to start</p>
                    </div>
                </div>

                <!-- Hidden canvas for capture -->
                <canvas ref="canvasRef" class="hidden"></canvas>

                <!-- Controls -->
                <div class="flex gap-2 justify-center">
                    <Button v-if="!cameraActive && !capturedImage" @click="startCamera">Open Camera</Button>
                    <Button variant="danger" v-if="cameraActive && !capturedImage" @click="capture">
                        <span class="w-3 h-3 rounded-full bg-white inline-block"></span> Capture
                    </Button>
                    <button v-if="capturedImage" @click="retake" class="px-4 py-2 border rounded-lg text-gray-700 text-sm hover:bg-gray-50">Retake</button>
                    <Button variant="success" v-if="capturedImage" @click="confirmCapture">Use This Photo</Button>
                </div>
            </div>
        </div>
    </div>
</template>
