<template>
    <!--
        PermissionGate — declaratively gates UI sections by Spatie permission.

        Usage:
            <PermissionGate permission="waive_fee">
                <WaiveButton />
            </PermissionGate>

            <!-- any-match (show if user has at least one) -->
            <PermissionGate :any="['edit_fee', 'delete_fee']">
                <FeeActions />
            </PermissionGate>

            <!-- all-match (show only if user has every permission) -->
            <PermissionGate :all="['create_payroll', 'edit_payroll']">
                <PayrollForm />
            </PermissionGate>

            <!-- custom fallback slot -->
            <PermissionGate permission="delete_students">
                <DeleteButton />
                <template #fallback>
                    <span class="text-xs text-gray-400">No delete access</span>
                </template>
            </PermissionGate>
    -->
    <slot v-if="allowed" />
    <slot v-else name="fallback" />
</template>

<script setup>
import { computed } from 'vue'
import { usePermissions } from '@/Composables/usePermissions'

const props = defineProps({
    /** Exact Spatie permission name. Renders slot if user has it. */
    permission: {
        type: String,
        default: null,
    },
    /** Any-match: renders slot if user has at least ONE of these permissions. */
    any: {
        type: Array,
        default: null,
    },
    /** All-match: renders slot only if user has ALL of these permissions. */
    all: {
        type: Array,
        default: null,
    },
})

const { can, canAny, canAll } = usePermissions()

const allowed = computed(() => {
    if (props.permission) return can(props.permission)
    if (props.any?.length)  return canAny(props.any)
    if (props.all?.length)  return canAll(props.all)
    // No constraint specified — allow by default (acts as a passthrough)
    return true
})
</script>
