<?php

namespace App\Contracts;

use App\Models\Student;
use App\Models\User;

interface NotificationServiceContract
{
    public function sendSms($recipient, $message, $templateId = null, $userId = null, $templateData = []): bool;

    public function sendWhatsApp($recipient, $templateId, $parameters = [], $userId = null, $languageCode = 'en'): bool;

    public function notifyAttendance($student, $status): void;

    public function notifyFeePayment($payment): void;

    public function notifyFeeDue($student, $amount, $dueDate): void;

    public function notifyOtp($user, $otp): void;

    public function notifyExamPublished($examSchedule): void;

    public function sendVoiceCall($recipient, $audioUrl = null, $content = null, $userId = null): bool;
}
