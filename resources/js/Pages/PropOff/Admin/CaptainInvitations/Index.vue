<script setup>
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '@/Components/Base/PageHeader.vue';
import Button from '@/Components/Base/Button.vue';
import Badge from '@/Components/Base/Badge.vue';
import Icon from '@/Components/Base/Icon.vue';
import TextField from '@/Components/Form/TextField.vue';
import DatePicker from '@/Components/Form/DatePicker.vue';
import Toast from '@/Components/Feedback/Toast.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    invitations: {
        type: Array,
        required: true,
    },
});

const showCopiedToast = ref(false);

const createForm = useForm({
    max_uses: null,
    expires_at: null,
});

const submitCreate = () => {
    createForm.post(route('propoff.admin.events.captain-invitations.store', props.event.id), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
        },
    });
};

const deactivateInvitation = (invitationId) => {
    router.post(route('propoff.admin.events.captain-invitations.deactivate', [props.event.id, invitationId]), {}, {
        preserveScroll: true,
    });
};

const reactivateInvitation = (invitationId) => {
    router.post(route('propoff.admin.events.captain-invitations.reactivate', [props.event.id, invitationId]), {}, {
        preserveScroll: true,
    });
};

const deleteInvitation = (invitationId) => {
    if (confirm('Are you sure you want to delete this invitation?')) {
        router.delete(route('propoff.admin.events.captain-invitations.destroy', [props.event.id, invitationId]), {
            preserveScroll: true,
        });
    }
};

const copyUrl = (url) => {
    navigator.clipboard.writeText(url);
    showCopiedToast.value = true;
    setTimeout(() => {
        showCopiedToast.value = false;
    }, 3000);
};
</script>

<template>
    <Head title="Captain Invitations" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="Captain Invitations"
                subtitle="Manage captain invitation links for this event"
                :crumbs="[
                    { label: 'Events', href: route('propoff.admin.events.index') },
                    { label: event.name, href: route('propoff.admin.events.show', event.id) },
                    { label: 'Captain Invitations' }
                ]"
            >
                <template #metadata>
                    <span class="font-medium text-body">{{ event.name }}</span>
                    <span class="text-subtle mx-2">•</span>
                    <span class="text-muted">{{ invitations.length }} invitation{{ invitations.length !== 1 ? 's' : '' }}</span>
                </template>
            </PageHeader>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Info Banner -->
                <div class="bg-primary/10 border-l-4 border-primary/30 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <Icon name="circle-info" class="text-primary" />
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-semibold text-primary mb-1">About Captain Invitations</h3>
                            <p class="text-sm text-primary">
                                Captain invitations allow users to create groups and become captains for this event.
                                Share the invitation URL with users who should have captain privileges.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Create Invitation -->
                <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-body">Create New Captain Invitation Link</h3>
                        </div>

                        <form @submit.prevent="submitCreate" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <TextField
                                    v-model="createForm.max_uses"
                                    type="number"
                                    label="Max Uses (Optional)"
                                    placeholder="Unlimited"
                                    hint="Leave empty for unlimited uses"
                                    :error="createForm.errors.max_uses"
                                />

                                <DatePicker
                                    v-model="createForm.expires_at"
                                    label="Expires At (Optional)"
                                    hint="Leave empty for no expiration"
                                    enable-time
                                    :error="createForm.errors.expires_at"
                                />
                            </div>

                            <div class="flex justify-end">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    icon="link"
                                    :loading="createForm.processing"
                                >
                                    Generate Link
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Invitations List -->
                <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg border border-border">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-body mb-4">Active Invitations ({{ invitations.length }})</h3>

                        <div v-if="invitations.length === 0" class="text-center py-12">
                            <p class="text-muted">No captain invitations yet.</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="invitation in invitations"
                                :key="invitation.id"
                                class="border rounded-lg p-4"
                                :class="invitation.can_be_used ? 'border-success/30 bg-success/10' : 'border-border bg-surface-elevated'"
                            >
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <!-- Status Badge -->
                                        <div class="flex gap-2 mb-3">
                                            <Badge
                                                v-if="invitation.is_active && invitation.can_be_used"
                                                variant="success-soft"
                                                size="sm"
                                            >
                                                <Icon name="circle-check" size="xs" class="mr-1" />
                                                Active
                                            </Badge>
                                            <Badge
                                                v-else
                                                variant="danger-soft"
                                                size="sm"
                                            >
                                                <Icon name="circle-xmark" size="xs" class="mr-1" />
                                                Inactive
                                            </Badge>
                                            <Badge
                                                v-if="invitation.max_uses"
                                                variant="primary-soft"
                                                size="sm"
                                            >
                                                {{ invitation.times_used }} / {{ invitation.max_uses }} uses
                                            </Badge>
                                            <Badge
                                                v-else
                                                variant="primary-soft"
                                                size="sm"
                                            >
                                                {{ invitation.times_used }} uses
                                            </Badge>
                                        </div>

                                        <!-- Invitation URL -->
                                        <div class="mb-3">
                                            <label class="text-xs text-muted block mb-1">Invitation URL</label>
                                            <div class="flex gap-2">
                                                <TextField
                                                    :model-value="invitation.url"
                                                    readonly
                                                    class="flex-1 font-mono text-sm"
                                                />
                                                <Button
                                                    variant="muted"
                                                    size="sm"
                                                    icon="copy"
                                                    @click="copyUrl(invitation.url)"
                                                >
                                                    Copy
                                                </Button>
                                                <Button
                                                    v-if="invitation.is_active"
                                                    variant="accent"
                                                    size="sm"
                                                    icon="pause"
                                                    @click="deactivateInvitation(invitation.id)"
                                                >
                                                    Deactivate
                                                </Button>
                                                <Button
                                                    v-else
                                                    variant="success"
                                                    size="sm"
                                                    icon="play"
                                                    @click="reactivateInvitation(invitation.id)"
                                                >
                                                    Reactivate
                                                </Button>
                                                <Button
                                                    v-if="invitation.times_used === 0"
                                                    variant="danger"
                                                    size="sm"
                                                    icon="trash"
                                                    @click="deleteInvitation(invitation.id)"
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        </div>

                                        <!-- Details -->
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <span class="text-muted">Created by:</span>
                                                <span class="font-semibold text-body ml-2">{{ invitation.creator.name }}</span>
                                            </div>
                                            <div>
                                                <span class="text-muted">Created:</span>
                                                <span class="text-body ml-2">{{ new Date(invitation.created_at).toLocaleDateString() }}</span>
                                            </div>
                                            <div v-if="invitation.expires_at">
                                                <span class="text-muted">Expires:</span>
                                                <span class="text-body ml-2">{{ new Date(invitation.expires_at).toLocaleString() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <Toast :show="showCopiedToast" variant="success" message="Invitation URL copied to clipboard!" />
    </AuthenticatedLayout>
</template>
