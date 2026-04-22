<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Transport Receipt</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="bg-blue-600 px-6 py-4 text-center">
            <div class="inline-block bg-white text-blue-600 rounded-full p-2 mb-2">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h1 class="text-xl font-bold text-white">Verified Transport Receipt</h1>
            <p class="text-blue-100 text-sm">Issued by {{ $payment->school->name ?? 'School' }}</p>
        </div>

        <div class="p-6 space-y-4">
            <div class="flex justify-between border-b pb-3">
                <span class="text-gray-500 font-medium">Receipt No.</span>
                <span class="font-bold text-gray-900">{{ $payment->receipt_no }}</span>
            </div>
            <div class="flex justify-between border-b pb-3">
                <span class="text-gray-500 font-medium">Student Name</span>
                <span class="font-bold text-gray-900">{{ $payment->student->first_name }} {{ $payment->student->last_name }}</span>
            </div>
            <div class="flex justify-between border-b pb-3">
                <span class="text-gray-500 font-medium">Admission No.</span>
                <span class="text-gray-900">{{ $payment->student->admission_no }}</span>
            </div>
            @if($payment->allocation?->route)
            <div class="flex justify-between border-b pb-3">
                <span class="text-gray-500 font-medium">Route</span>
                <span class="text-gray-900">{{ $payment->allocation->route->route_name }}</span>
            </div>
            @endif
            <div class="flex justify-between border-b pb-3">
                <span class="text-gray-500 font-medium">Amount Paid</span>
                <span class="font-bold text-blue-600 text-lg">₹{{ number_format($payment->amount_paid, 2) }}</span>
            </div>
            <div class="flex justify-between pb-1">
                <span class="text-gray-500 font-medium">Payment Date</span>
                <span class="text-gray-900">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d-M-Y') }}</span>
            </div>
        </div>

        <div class="bg-gray-50 px-6 py-4 text-center text-xs text-gray-500 border-t">
            This digital receipt confirms the transport fee transaction stored in the official School ERP database.
        </div>
    </div>

</body>
</html>
