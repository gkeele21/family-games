<script setup lang="ts">
import StandardLayout from '@/Layouts/StandardLayout.vue';
import Button from '@/Components/Base/Button.vue';
import Modal from '@/Components/Base/Modal.vue';
import TextField from '@/Components/Form/TextField.vue';
import Select from '@/Components/Form/Select.vue';
import Toggle from '@/Components/Form/Toggle.vue';
import Confirm from '@/Components/Feedback/Confirm.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface Answer { id: number | null; answer_text: string; points: number; display_order: number }
interface Category { id: number; name: string }
interface Question {
    id: number;
    question_text: string;
    difficulty: 'easy' | 'medium' | 'hard' | null;
    round_type: 'regular' | 'final';
    answer_letter: string | null;
    is_active: boolean;
    is_official: boolean;
    times_used: number;
    category_id: number | null;
    category: Category | null;
    answers: Answer[];
}
interface GameTypeOption { id: number; name: string; slug: string; questions_count: number }

interface Props {
    gameTypes: GameTypeOption[];
    activeSlug: string;
    categories: Category[];
    questions: Question[];
}
const props = defineProps<Props>();

const activeGame = computed(() => props.gameTypes.find((g) => g.slug === props.activeSlug));
const isAmericaSays = computed(() => props.activeSlug === 'america-says');
const isOodles = computed(() => props.activeSlug === 'oodles');
// Games with a final/end round where questions can be authored for it.
const hasFinalRound = computed(() => ['america-says', 'family-feud'].includes(props.activeSlug));
const finalLabel = computed(() => (props.activeSlug === 'family-feud' ? 'Fast Money' : 'Final round'));
const roundTypeOptions = computed(() => [
    { value: 'regular', label: 'Regular play' },
    { value: 'final', label: finalLabel.value },
]);
const roundFilterOptions = computed(() => [
    { value: 'regular', label: 'Standard' },
    { value: 'final', label: finalLabel.value },
]);
// Only Family Feud assigns points to individual answers. America Says scores per
// round (set in setup); Oodles scores by cards won — neither uses answer points.
const showPoints = computed(() => props.activeSlug === 'family-feud');

const switchGame = (slug: string) => {
    if (slug === props.activeSlug) return;
    router.get(route('questions.index', { game: slug }), {}, { preserveScroll: true, preserveState: false });
};

// ---- Filters (client-side) ----
const search = ref('');
const categoryFilter = ref<number | ''>('');
const difficultyFilter = ref<string>('');
const sourceFilter = ref<string>('');
const statusFilter = ref<string>('');
const roundFilter = ref<string>('');
const openId = ref<number | null>(null);

const categoryFilterOptions = computed(() => props.categories.map((c) => ({ value: c.id, label: c.name })));
const difficultyOptions = [
    { value: 'easy', label: 'Easy' },
    { value: 'medium', label: 'Medium' },
    { value: 'hard', label: 'Hard' },
];
const sourceOptions = [
    { value: 'official', label: 'From the show' },
    { value: 'custom', label: 'Made up' },
];
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const filtered = computed(() => {
    const s = search.value.trim().toLowerCase();
    return props.questions.filter((q) => {
        if (statusFilter.value === 'active' && !q.is_active) return false;
        if (statusFilter.value === 'inactive' && q.is_active) return false;
        if (categoryFilter.value && q.category_id !== categoryFilter.value) return false;
        if (difficultyFilter.value && q.difficulty !== difficultyFilter.value) return false;
        if (sourceFilter.value === 'official' && !q.is_official) return false;
        if (sourceFilter.value === 'custom' && q.is_official) return false;
        if (roundFilter.value && q.round_type !== roundFilter.value) return false;
        if (s && !q.question_text.toLowerCase().includes(s)) return false;
        return true;
    });
});

const difficultyClass = (d: string) =>
    d === 'easy' ? 'text-primary border-primary/40'
    : d === 'hard' ? 'text-danger border-danger/40'
    : 'text-warning border-warning/40';
const difficultyLabel = (d: string) => d.charAt(0).toUpperCase() + d.slice(1);

// ---- Editor ----
const showEditor = ref(false);
const editorMode = ref<'add' | 'edit'>('add');
const form = useForm({
    id: null as number | null,
    game_type_id: null as number | null,
    category_id: null as number | null,
    question_text: '',
    difficulty: null as 'easy' | 'medium' | 'hard' | null,
    round_type: 'regular' as 'regular' | 'final',
    answer_letter: '',
    is_active: true,
    is_official: false,
    answers: [] as Answer[],
});

const categoryOptions = computed(() => props.categories.map((c) => ({ value: c.id, label: c.name })));
const defaultAnswerCount = computed(() => (isAmericaSays.value ? 7 : isOodles.value ? 1 : 5));
const blankAnswer = (): Answer => ({ id: null, answer_text: '', points: 0, display_order: 0 });

const openAdd = () => {
    editorMode.value = 'add';
    form.clearErrors();
    form.id = null;
    form.game_type_id = activeGame.value?.id ?? null;
    form.category_id = null;
    form.question_text = '';
    form.difficulty = null;
    form.round_type = 'regular';
    form.answer_letter = '';
    form.is_active = true;
    form.is_official = true;
    form.answers = Array.from({ length: defaultAnswerCount.value }, blankAnswer);
    showEditor.value = true;
};
const openEdit = (q: Question) => {
    editorMode.value = 'edit';
    form.clearErrors();
    form.id = q.id;
    form.game_type_id = activeGame.value?.id ?? null;
    form.category_id = q.category_id;
    form.question_text = q.question_text;
    form.difficulty = q.difficulty;
    form.round_type = q.round_type;
    form.answer_letter = q.answer_letter ?? '';
    form.is_active = q.is_active;
    form.is_official = q.is_official;
    form.answers = q.answers.map((a) => ({ ...a }));
    if (isOodles.value && !form.answers.length) form.answers = [blankAnswer()];
    showEditor.value = true;
};
const addAnswer = () => form.answers.push(blankAnswer());
const removeAnswer = (i: number) => form.answers.splice(i, 1);

// Final questions hold a specific number of answers (America Says slots them by
// answer count, 1–4). Rather than start at the 7-row regular default and make the
// host delete rows, a Final question starts at 1 and this resizes the list.
const setAnswerCount = (n: number | string) => {
    if (n === '' || n === null || n === undefined) return;
    let target = Math.floor(Number(n));
    if (isNaN(target)) return;
    // Final questions are a set standard of 1–4 answers.
    target = Math.max(1, Math.min(4, target));
    const cur = form.answers.length;
    if (target > cur) {
        for (let k = cur; k < target; k++) form.answers.push(blankAnswer());
    } else if (target < cur) {
        form.answers.splice(target);
    }
};

// User-driven Round change (not the programmatic set during open): switching to
// the final round drops to a single answer row; switching back restores the
// regular default so the host doesn't have to rebuild the list by hand.
const onRoundChange = (val: 'regular' | 'final') => {
    if (val === form.round_type) return;
    form.round_type = val;
    setAnswerCount(val === 'final' ? 1 : defaultAnswerCount.value);
};

const save = () => {
    const opts = { preserveScroll: true, onSuccess: () => (showEditor.value = false) };
    if (editorMode.value === 'add') form.post(route('questions.store'), opts);
    else form.patch(route('questions.update', form.id!), opts);
};

// ---- Inline category add ----
const addingCategory = ref(false);
const newCategoryName = ref('');
const saveCategory = () => {
    const name = newCategoryName.value.trim();
    if (!name) return;
    router.post(route('questions.categories.store'), { game_type_id: activeGame.value?.id, name }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { addingCategory.value = false; newCategoryName.value = ''; },
    });
};

// ---- Delete ----
const confirmState = ref<{ show: boolean; q: Question | null }>({ show: false, q: null });
const askDelete = (q: Question) => (confirmState.value = { show: true, q });
const runDelete = () => {
    const q = confirmState.value.q;
    confirmState.value = { show: false, q: null };
    if (q) router.delete(route('questions.destroy', q.id), { preserveScroll: true });
};

const inputClass = 'w-full rounded-lg border-border bg-surface-inset text-body placeholder:text-muted focus:border-primary focus:ring-primary';
</script>

<template>
    <Head title="Question Library" />

    <StandardLayout sticky-header>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold text-body">Question Library</h1>
                    <p class="mt-1 text-sm text-muted">Manage the question bank each game draws from. Retire a question in the editor and it stops appearing in new games — no delete needed.</p>
                </div>
                <Button variant="primary" size="md" @click="openAdd">＋ Add question</Button>
            </div>
        </template>

        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- game type tabs -->
            <div class="mb-5 flex gap-1 border-b border-border">
                <button
                    v-for="gt in gameTypes"
                    :key="gt.id"
                    :class="['-mb-px border-b-2 px-4 py-2 text-sm font-semibold transition',
                        gt.slug === activeSlug ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-body']"
                    @click="switchGame(gt.slug)"
                >
                    {{ gt.name }}<span class="ml-2 text-xs text-subtle">{{ gt.questions_count }}</span>
                </button>
            </div>

            <!-- toolbar -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="min-w-[180px] flex-1">
                    <TextField v-model="search" placeholder="Search questions…" />
                </div>
                <Select v-model="categoryFilter" :options="categoryFilterOptions" allow-empty empty-label="All categories" />
                <Select v-model="difficultyFilter" :options="difficultyOptions" allow-empty empty-label="Any difficulty" />
                <Select v-model="sourceFilter" :options="sourceOptions" allow-empty empty-label="Any source" />
                <Select v-if="hasFinalRound" v-model="roundFilter" :options="roundFilterOptions" allow-empty empty-label="Any round" />
                <Select v-model="statusFilter" :options="statusOptions" allow-empty empty-label="All statuses" />
            </div>

            <p class="mb-4 text-sm text-subtle"><span class="text-muted">{{ activeGame?.questions_count ?? 0 }}</span> questions · showing {{ filtered.length }}</p>

            <!-- list -->
            <div class="space-y-2.5">
                <div v-for="q in filtered" :key="q.id" :class="['rounded-xl border bg-surface', openId === q.id ? 'border-border-strong' : 'border-border']">
                    <div class="flex cursor-pointer items-center gap-3 p-4" @click="openId = openId === q.id ? null : q.id">
                        <span :class="['w-3.5 flex-none text-xs text-subtle transition', openId === q.id ? 'rotate-90' : '']">▶</span>
                        <span class="flex min-w-0 flex-1 items-center gap-2.5">
                            <span :class="['truncate font-semibold', q.is_active ? 'text-body' : 'text-muted']">{{ q.question_text }}</span>
                            <span class="flex-none whitespace-nowrap text-xs text-subtle">{{ q.answers.length }} {{ q.answers.length === 1 ? 'answer' : 'answers' }}</span>
                            <span v-if="q.round_type === 'final'" class="flex-none whitespace-nowrap rounded-full border border-info/40 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-info" :title="`Reserved for the ${finalLabel} round`">Final</span>
                            <span v-if="q.is_official" class="flex-none whitespace-nowrap rounded-full border border-gold/40 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-gold" title="From the show">★ Show</span>
                            <span v-if="!q.is_active" class="flex-none whitespace-nowrap rounded-full border border-border-strong bg-surface-overlay px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-muted">Inactive</span>
                        </span>
                        <span class="flex flex-none items-center gap-3.5">
                            <span v-if="q.category" class="whitespace-nowrap rounded-full border border-border bg-surface-inset px-2.5 py-0.5 text-xs text-muted">{{ q.category.name }}</span>
                            <span v-if="q.difficulty" :class="['whitespace-nowrap rounded-full border bg-surface-inset px-2.5 py-0.5 text-xs', difficultyClass(q.difficulty)]">{{ difficultyLabel(q.difficulty) }}</span>
                            <span class="whitespace-nowrap text-xs text-subtle">used <span class="text-muted">{{ q.times_used }}×</span></span>
                            <span class="cursor-pointer whitespace-nowrap text-sm font-semibold text-warning hover:underline" @click.stop="openEdit(q)">Edit</span>
                        </span>
                    </div>

                    <!-- expanded preview -->
                    <div v-if="openId === q.id" class="border-t border-border bg-surface-inset px-5 py-3">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs uppercase tracking-wide text-subtle">
                                    <th class="w-8 py-1 text-left font-semibold">#</th>
                                    <th class="py-1 text-left font-semibold">{{ isOodles ? 'Answer (letter ' + (q.answer_letter || '?') + ')' : 'Answer' }}</th>
                                    <th v-if="showPoints" class="py-1 text-right font-semibold">Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(a, i) in q.answers" :key="a.id ?? i" class="border-t border-border">
                                    <td class="py-1.5 text-subtle">{{ i + 1 }}</td>
                                    <td class="py-1.5 text-body">{{ a.answer_text }}</td>
                                    <td v-if="showPoints" class="py-1.5 text-right font-semibold text-gold">{{ a.points }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="!filtered.length" class="rounded-xl border border-border bg-surface py-12 text-center text-muted">
                    No questions {{ props.questions.length ? 'match your filters' : 'yet — add one to get started' }}.
                </div>
            </div>
        </div>

        <!-- Add / Edit modal -->
        <Modal :show="showEditor" max-width="lg" @close="showEditor = false">
            <div>
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h3 class="text-base font-semibold text-body">{{ editorMode === 'add' ? 'Add' : 'Edit' }} question — {{ activeGame?.name }}</h3>
                    <button class="text-lg text-subtle hover:text-body" @click="showEditor = false">✕</button>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <TextField v-model="form.question_text" label="Question" placeholder="e.g., Name a fruit you'd put in a smoothie" :error="form.errors.question_text" />

                    <div class="grid grid-cols-2 items-end gap-4" :class="isOodles ? 'sm:grid-cols-3' : ''">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-body">Category</label>
                            <div v-if="!addingCategory" class="flex items-center gap-2">
                                <Select v-model="form.category_id" :options="categoryOptions" allow-empty empty-label="No category" class="flex-1" />
                                <Button variant="ghost" size="xs" @click="addingCategory = true">＋ New</Button>
                            </div>
                            <div v-else class="flex items-center gap-2">
                                <input v-model="newCategoryName" :class="inputClass" placeholder="New category" @keyup.enter="saveCategory" />
                                <Button variant="primary" size="xs" @click="saveCategory">Add</Button>
                                <button class="text-sm text-muted hover:text-body" @click="addingCategory = false">✕</button>
                            </div>
                        </div>
                        <Select v-model="form.difficulty" :options="difficultyOptions" label="Difficulty" allow-empty :empty-value="null" empty-label="No difficulty" />
                        <TextField v-if="isOodles" v-model="form.answer_letter" label="Letter (A–Z)" placeholder="A" />
                    </div>

                    <div v-if="hasFinalRound" class="flex items-end gap-4">
                        <div class="max-w-[220px] flex-1">
                            <Select :model-value="form.round_type" :options="roundTypeOptions" label="Round" @update:model-value="onRoundChange" />
                        </div>
                        <!-- Final questions carry a set number of answers; this sizes
                             the list below (America Says slots them by answer count). -->
                        <div v-if="!isOodles && form.round_type === 'final'" class="w-24">
                            <label class="mb-1.5 block text-sm font-medium text-body">Answers</label>
                            <input
                                :value="form.answers.length"
                                type="number"
                                min="1"
                                max="4"
                                :class="inputClass"
                                @input="setAnswerCount(($event.target as HTMLInputElement).value)"
                            />
                        </div>
                    </div>

                    <!-- Oodles: one answer, no points (scored by cards won) -->
                    <div v-if="isOodles">
                        <label class="mb-1.5 block text-sm font-medium text-body">Answer</label>
                        <input v-model="form.answers[0].answer_text" :class="inputClass" placeholder="The answer — a word starting with the letter" />
                        <p class="mt-1.5 text-xs text-subtle">Oodles is scored by cards won, so answers don't carry points.</p>
                    </div>

                    <!-- America Says / Family Feud: list of answers -->
                    <div v-else>
                        <div class="overflow-hidden rounded-lg border border-border bg-surface-inset">
                            <div class="flex items-center justify-between border-b border-border px-3 py-2">
                                <span class="text-xs uppercase tracking-wide text-subtle">Answers</span>
                                <Button variant="primary" size="xs" @click="addAnswer">＋ Add</Button>
                            </div>
                            <div v-for="(a, i) in form.answers" :key="i" class="flex items-center gap-2 border-t border-border/60 px-3 py-2 first:border-t-0">
                                <span class="w-5 text-right text-xs text-subtle">{{ i + 1 }}</span>
                                <input v-model="a.answer_text" :class="[inputClass, 'flex-1 !bg-surface']" placeholder="Answer" />
                                <input v-if="showPoints" v-model.number="a.points" type="number" min="0" :class="[inputClass, 'w-20 !bg-surface text-right']" />
                                <button type="button" tabindex="-1" class="text-muted hover:text-danger" @click="removeAnswer(i)">✕</button>
                            </div>
                        </div>
                        <p v-if="isAmericaSays" class="mt-1.5 text-xs text-subtle">America Says reveals these answers on the board — round points are set in the game's setup, so there are no per-answer points here.</p>
                    </div>

                    <div class="flex items-center gap-6">
                        <Toggle v-model="form.is_official" label="From the show" />
                        <Toggle v-model="form.is_active" label="Active" />
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-border px-5 py-4">
                    <Button v-if="editorMode === 'edit'" variant="danger" size="md" @click="showEditor = false; askDelete(props.questions.find(x => x.id === form.id)!)">Delete</Button>
                    <span v-else></span>
                    <div class="flex gap-3">
                        <Button variant="outline" size="md" @click="showEditor = false">Cancel</Button>
                        <Button variant="primary" size="md" :loading="form.processing" @click="save">Save</Button>
                    </div>
                </div>
            </div>
        </Modal>

        <Confirm
            :show="confirmState.show"
            title="Delete question?"
            :message="`Delete “${confirmState.q?.question_text}”? This can't be undone. To keep it out of games without losing it, set it inactive instead.`"
            confirm-text="Delete"
            variant="danger"
            @confirm="runDelete"
            @cancel="confirmState = { show: false, q: null }"
            @close="confirmState = { show: false, q: null }"
        />
    </StandardLayout>
</template>
