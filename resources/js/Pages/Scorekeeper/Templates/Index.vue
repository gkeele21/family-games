<script setup lang="ts">
import DangerButton from '@/Components/Scorekeeper/DangerButton.vue';
import PrimaryButton from '@/Components/Scorekeeper/PrimaryButton.vue';
import ScorekeeperLayout from '@/Layouts/ScorekeeperLayout.vue';
import SecondaryButton from '@/Components/Scorekeeper/SecondaryButton.vue';
import TemplateFormFields from '@/Components/Scorekeeper/TemplateFormFields.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ScoreField {
    key?: string;
    label: string;
    counts_toward_total: boolean;
    color?: string | null;
}

interface Template {
    id: number;
    name: string;
    game_type_id: number | null;
    game_type: { id: number; name: string } | null;
    target_score: number | null;
    low_score_wins: boolean;
    max_rounds: number | null;
    team_based: boolean;
    allow_self_scoring: boolean;
    is_global: boolean;
    score_fields: ScoreField[];
    is_system: boolean;
    household_id: number | null;
}

const props = defineProps<{
    household: { id: number; name: string };
    templates: Template[];
    games: Array<{ id: number; name: string }>;
}>();

const page = usePage();

const defaultFields = (): ScoreField[] => [
    { label: 'Score', counts_toward_total: true, color: null },
];

const editingId = ref<number | null>(null);
const form = useForm({
    name: '',
    game_type_id: null as number | null,
    new_game_name: null as string | null,
    target_score: null as number | null,
    low_score_wins: false,
    max_rounds: null as number | null,
    team_based: false,
    allow_self_scoring: false,
    is_global: false,
    score_fields: defaultFields(),
});

const startCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const startEdit = (t: Template) => {
    editingId.value = t.id;
    form.name = t.name;
    form.game_type_id = t.game_type_id;
    form.new_game_name = null;
    form.target_score = t.target_score;
    form.low_score_wins = t.low_score_wins;
    form.max_rounds = t.max_rounds;
    form.team_based = t.team_based;
    form.allow_self_scoring = t.allow_self_scoring;
    form.is_global = t.is_global;
    form.score_fields = (t.score_fields?.length ? t.score_fields : defaultFields()).map(
        (f) => ({
            label: f.label,
            counts_toward_total: f.counts_toward_total,
            color: f.color ?? null,
        }),
    );
    form.clearErrors();
};

const submit = () => {
    if (editingId.value === null) {
        form.post(
            route('scorekeeper.households.templates.store', props.household.id),
            { preserveScroll: true, onSuccess: () => form.reset() },
        );
    } else {
        form.patch(route('scorekeeper.templates.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => startCreate(),
        });
    }
};

const destroy = (t: Template) => {
    if (!confirm(`Delete template "${t.name}"?`)) return;
    router.delete(route('scorekeeper.templates.destroy', t.id), {
        preserveScroll: true,
    });
};

const onCopySelect = (t: Template, event: Event) => {
    const select = event.target as HTMLSelectElement;
    const targetId = Number(select.value);
    select.value = '';
    if (!targetId) return;
    router.post(
        route('scorekeeper.templates.copy', t.id),
        { target_household_id: targetId },
        { preserveScroll: true },
    );
};

const rules = (t: Template) => {
    const parts: string[] = [];
    if (t.target_score !== null) parts.push(`to ${t.target_score}`);
    if (t.max_rounds !== null) parts.push(`${t.max_rounds} rounds`);
    parts.push(t.low_score_wins ? 'low score wins' : 'high score wins');
    if (t.team_based) parts.push('teams');
    const fieldCount = t.score_fields?.length ?? 1;
    if (fieldCount > 1) parts.push(`${fieldCount} scores/round`);
    return parts.join(' · ');
};
</script>

<template>
    <Head title="Game templates" />

    <ScorekeeperLayout :household="household" tab="templates">
        <div class="py-10">
            <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-lg border border-primary/30 bg-primary/10 p-4 text-sm text-primary"
                >
                    {{ page.props.flash.success }}
                </div>

                <!-- Create / edit form -->
                <div class="overflow-hidden rounded-lg border border-border bg-surface p-6">
                    <h3 class="mb-4 text-lg font-medium text-body">
                        {{
                            editingId === null
                                ? 'New game template'
                                : 'Edit game template'
                        }}
                    </h3>
                    <form class="space-y-4" @submit.prevent="submit">
                        <TemplateFormFields
                            :form="form"
                            :games="games"
                            id-prefix="tpl"
                        />
                        <div class="flex gap-3">
                            <PrimaryButton :disabled="form.processing">
                                {{
                                    editingId === null
                                        ? 'Create template'
                                        : 'Save changes'
                                }}
                            </PrimaryButton>
                            <SecondaryButton
                                v-if="editingId !== null"
                                type="button"
                                @click="startCreate"
                                >Cancel</SecondaryButton
                            >
                        </div>
                    </form>
                </div>

                <!-- List -->
                <div class="overflow-hidden rounded-lg border border-border bg-surface">
                    <ul class="divide-y divide-border">
                        <li
                            v-for="t in templates"
                            :key="t.id"
                            class="flex items-center justify-between px-6 py-4"
                        >
                            <div>
                                <p class="font-medium text-body">
                                    {{ t.name }}
                                    <span
                                        v-if="t.is_system"
                                        class="ml-2 rounded-full bg-warning/15 px-2 py-0.5 text-xs font-medium text-warning"
                                        >System</span
                                    >
                                    <span
                                        v-else-if="t.is_global"
                                        class="ml-2 rounded-full bg-primary/15 px-2 py-0.5 text-xs font-medium text-primary"
                                        >Shared</span
                                    >
                                </p>
                                <p class="text-sm text-muted">
                                    <span v-if="t.game_type"
                                        >{{ t.game_type.name }} · </span
                                    >{{ rules(t) }}
                                </p>
                            </div>
                            <div v-if="!t.is_system" class="flex items-center gap-2">
                                <select
                                    class="rounded-lg border-border-strong bg-surface-inset text-sm text-body focus:border-primary focus:ring-primary"
                                    @change="onCopySelect(t, $event)"
                                >
                                    <option value="">Copy to…</option>
                                    <option
                                        v-for="h in page.props.households"
                                        :key="h.id"
                                        :value="h.id"
                                    >
                                        {{ h.name
                                        }}{{
                                            h.id === household.id
                                                ? ' (duplicate here)'
                                                : ''
                                        }}
                                    </option>
                                </select>
                                <SecondaryButton @click="startEdit(t)"
                                    >Edit</SecondaryButton
                                >
                                <DangerButton @click="destroy(t)"
                                    >Delete</DangerButton
                                >
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </ScorekeeperLayout>
</template>
