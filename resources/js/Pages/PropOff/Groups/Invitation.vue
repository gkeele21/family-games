<template>
    <Head :title="`Group Invitation - ${group.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader
                title="Group Invitation"
                subtitle="Share the join code, invitation link, or QR code to invite members to your group."
                :crumbs="[
                    { label: 'Home', href: route('propoff.play.hub', { code: group.code }) },
                    { label: 'Join Info' }
                ]"
            >
                <template #actions>
                    <Button
                        :variant="invitation.is_active ? 'danger' : 'success'"
                        size="sm"
                        :loading="toggleForm.processing"
                        @click="toggleInvitation"
                    >
                        <Icon :name="invitation.is_active ? 'link-slash' : 'link'" class="mr-2" size="sm" />
                        {{ invitation.is_active ? 'Deactivate' : 'Activate' }}
                    </Button>
                    <Button
                        variant="accent"
                        size="sm"
                        @click="showRegenerateModal = true"
                    >
                        <Icon name="arrows-rotate" class="mr-2" size="sm" />
                        Regenerate
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        @click="showSettingsModal = true"
                    >
                        <Icon name="gear" class="mr-2" size="sm" />
                        Settings
                    </Button>
                </template>
            </PageHeader>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Group Info -->
                <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-border">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-warning/10 rounded-full flex items-center justify-center">
                            <UserGroupIcon class="w-6 h-6 text-warning" />
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-body">{{ group.name }}</h3>
                            <p class="text-sm text-muted mt-1">
                                Event: {{ group.event.name }}
                            </p>
                            <p class="text-xs text-subtle mt-1">
                                {{ formatDate(group.event.event_date) }}
                            </p>
                            <p class="text-sm text-body mt-2">
                                Join Code: <span class="font-mono font-bold text-primary">{{ group.code }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Invitation Link Section -->
                <div class="bg-surface overflow-hidden shadow-sm sm:rounded-lg p-6 border border-border">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-body">Invitation Link</h3>
                        <span
                            :class="[
                                'px-3 py-1 rounded-full text-xs font-semibold',
                                invitation.is_active
                                    ? 'bg-success/20 text-success'
                                    : 'bg-danger/20 text-danger'
                            ]"
                        >
                            {{ invitation.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <!-- URL Display and Copy -->
                    <div class="bg-surface-elevated rounded-lg p-4 mb-4">
                        <label class="block text-sm font-medium text-muted mb-2">
                            Share this link with members
                        </label>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                :value="invitation.url"
                                readonly
                                class="flex-1 px-3 py-2 border border-border rounded-md bg-surface-inset text-body text-sm font-mono"
                            />
                            <button
                                @click="copyToClipboard"
                                class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-md hover:bg-primary/80 transition"
                            >
                                <ClipboardDocumentIcon class="w-5 h-5 mr-2" />
                                {{ copied ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>
                    </div>

                    <!-- QR Code -->
                    <div class="text-center py-6 border-t border-border pt-6">
                        <p class="text-sm text-muted mb-4">Scan QR Code to Join</p>
                        <div class="inline-block bg-white p-4 rounded-lg shadow-sm">
                            <div ref="qrcode" class="w-64 h-64"></div>
                        </div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-4">
                    <StatTile :value="invitation.times_used" label="Times Used" color="primary" />
                    <StatTile :value="invitation.max_uses || '∞'" label="Max Uses" color="warning" />
                </div>

                <!-- Warning if inactive -->
                <div v-if="!invitation.is_active" class="bg-warning/10 border border-warning/30 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <ExclamationTriangleIcon class="w-5 h-5 text-warning flex-shrink-0 mt-0.5" />
                        <div>
                            <h4 class="font-semibold text-warning">Invitation Inactive</h4>
                            <p class="text-sm text-warning mt-1">
                                This invitation link is currently inactive. New members cannot join using this link.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Regenerate Confirmation Modal -->
    <Teleport to="body">
        <div
            v-if="showRegenerateModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
            @click.self="showRegenerateModal = false"
        >
            <div class="bg-surface rounded-lg shadow-xl max-w-md w-full p-6 border border-border">
                <h3 class="text-lg font-semibold text-body mb-4">Regenerate Invitation Link?</h3>
                <p class="text-sm text-muted mb-6">
                    This will create a new invitation link and reset the usage counter. The old link will no longer work.
                </p>
                <div class="flex gap-3 justify-end">
                    <button
                        @click="showRegenerateModal = false"
                        class="px-4 py-2 border border-border rounded-md text-sm font-medium text-body hover:bg-surface-overlay"
                    >
                        Cancel
                    </button>
                    <button
                        @click="regenerateInvitation"
                        :disabled="regenerateForm.processing"
                        class="px-4 py-2 bg-warning text-white rounded-md text-sm font-medium hover:bg-warning/80 disabled:opacity-50"
                    >
                        {{ regenerateForm.processing ? 'Regenerating...' : 'Regenerate' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Settings Modal -->
    <Teleport to="body">
        <div
            v-if="showSettingsModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
            @click.self="showSettingsModal = false"
        >
            <div class="bg-surface rounded-lg shadow-xl max-w-md w-full p-6 border border-border">
                <h3 class="text-lg font-semibold text-body mb-4">Invitation Settings</h3>

                <form @submit.prevent="updateSettings" class="space-y-4">
                    <div>
                        <label for="max_uses" class="block text-sm font-medium text-muted mb-2">
                            Maximum Uses (leave empty for unlimited)
                        </label>
                        <input
                            id="max_uses"
                            type="number"
                            v-model.number="settingsForm.max_uses"
                            min="1"
                            class="w-full px-3 py-2 border border-border rounded-md bg-surface-inset text-body"
                            placeholder="Unlimited"
                        />
                        <p v-if="settingsForm.errors.max_uses" class="mt-1 text-sm text-danger">
                            {{ settingsForm.errors.max_uses }}
                        </p>
                    </div>

                    <div>
                        <label for="expires_at" class="block text-sm font-medium text-muted mb-2">
                            Expiration Date (leave empty for no expiration)
                        </label>
                        <input
                            id="expires_at"
                            type="datetime-local"
                            v-model="settingsForm.expires_at"
                            class="w-full px-3 py-2 border border-border rounded-md bg-surface-inset text-body"
                        />
                        <p v-if="settingsForm.errors.expires_at" class="mt-1 text-sm text-danger">
                            {{ settingsForm.errors.expires_at }}
                        </p>
                    </div>

                    <div class="flex gap-3 justify-end pt-4">
                        <button
                            type="button"
                            @click="showSettingsModal = false"
                            class="px-4 py-2 border border-border rounded-md text-sm font-medium text-body hover:bg-surface-overlay"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="settingsForm.processing"
                            class="px-4 py-2 bg-primary text-white rounded-md text-sm font-medium hover:bg-primary/80 disabled:opacity-50"
                        >
                            {{ settingsForm.processing ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/PropOffLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    UserGroupIcon,
    ClipboardDocumentIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import QRCode from 'qrcode';
import PageHeader from '@/Components/PropOff/Base/PageHeader.vue';
import StatTile from '@/Components/PropOff/Base/StatTile.vue';
import Button from '@/Components/PropOff/Base/Button.vue';
import Icon from '@/Components/PropOff/Base/Icon.vue';

const props = defineProps({
    group: Object,
    invitation: Object,
});

const copied = ref(false);
const showRegenerateModal = ref(false);
const showSettingsModal = ref(false);
const qrcode = ref(null);

const toggleForm = useForm({});
const regenerateForm = useForm({});
const settingsForm = useForm({
    max_uses: props.invitation.max_uses,
    expires_at: props.invitation.expires_at,
});

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const copyToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(props.invitation.url);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

const toggleInvitation = () => {
    toggleForm.post(route('propoff.groups.invitation.toggle', props.group.id), {
        preserveScroll: true,
    });
};

const regenerateInvitation = () => {
    regenerateForm.post(route('propoff.groups.invitation.regenerate', props.group.id), {
        preserveScroll: true,
        onSuccess: () => {
            showRegenerateModal.value = false;
            // Regenerate QR code with new URL
            generateQRCode();
        },
    });
};

const updateSettings = () => {
    settingsForm.patch(route('propoff.groups.invitation.update', props.group.id), {
        preserveScroll: true,
        onSuccess: () => {
            showSettingsModal.value = false;
        },
    });
};

const generateQRCode = () => {
    if (qrcode.value) {
        QRCode.toCanvas(props.invitation.url, {
            width: 256,
            margin: 2,
            color: {
                dark: '#000000',
                light: '#ffffff',
            },
        }, (error, canvas) => {
            if (error) {
                console.error('QR Code generation error:', error);
                return;
            }
            qrcode.value.innerHTML = '';
            qrcode.value.appendChild(canvas);
        });
    }
};

onMounted(() => {
    generateQRCode();
});
</script>
