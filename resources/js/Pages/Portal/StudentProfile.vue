<script setup>
import { Head } from '@inertiajs/vue3';
import SchoolLayout from '@/Layouts/SchoolLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
</script>

<template>
    <SchoolLayout title="Student Profile">
        <Head title="Student Profile" />
        <PageHeader
            title="Student Profile"
            subtitle="Starting from scratch..."
        />
    </SchoolLayout>
</template>
