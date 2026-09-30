<template>
    <section class="space-y-4">
        <h1 class="text-2xl font-bold text-slate-900">{{ t('fees.setupTitle') }}</h1>

        <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
        <p v-if="message" class="rounded-xl bg-green-50 px-3 py-2 text-sm text-green-800">{{ message }}</p>
        <div v-if="!setup" class="text-center text-slate-600">{{ t('common.loading') }}</div>

        <template v-else>
            <p v-if="!setup.academic_years.length" class="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ t('fees.noAcademicYear') }}</p>

            <!-- Fee heads -->
            <div class="space-y-2 rounded-2xl border border-slate-200 bg-white p-4">
                <h2 class="font-semibold text-slate-800">{{ t('fees.headsTitle') }}</h2>
                <p class="text-xs text-slate-500">{{ t('fees.headsHint') }}</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="h in setup.heads"
                        :key="h.id"
                        type="button"
                        class="rounded-full border px-3 py-1 text-sm"
                        :class="h.is_active ? 'border-blue-300 bg-blue-50 text-blue-800' : 'border-slate-200 text-slate-400 line-through'"
                        :title="h.is_active ? t('ops.deactivate') : t('ops.activate')"
                        @click="toggleHead(h)"
                    >
                        {{ h.name }}
                    </button>
                </div>
                <form class="flex gap-2" @submit.prevent="addHead">
                    <input v-model="newHead" required maxlength="100" :placeholder="t('fees.headPlaceholder')" class="flex-1 rounded-lg border border-slate-300 px-2 py-1.5" />
                    <button type="submit" class="rounded-lg border border-blue-300 bg-blue-50 px-3 text-sm font-semibold text-blue-800">{{ t('fees.add') }}</button>
                </form>
            </div>

            <!-- Fee structures -->
            <div v-if="setup.academic_years.length" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold text-slate-800">{{ t('fees.structuresTitle') }}</h2>
                    <select v-model="yearId" class="rounded-lg border border-slate-300 px-2 py-1 text-sm">
                        <option v-for="y in setup.academic_years" :key="y.id" :value="y.id">{{ y.name }}</option>
                    </select>
                </div>
                <p class="text-xs text-slate-500">{{ t('fees.structuresHint') }}</p>

                <div v-for="(rows, label) in structuresByLabel" :key="label" class="rounded-xl bg-slate-50 p-3">
                    <p class="font-semibold text-slate-800">{{ label }}</p>
                    <div v-for="s in rows" :key="s.id" class="flex items-center justify-between border-b border-slate-200 py-1 text-sm last:border-0">
                        <span>
                            {{ headName(s.fee_head_id) }} · {{ s.school_class_id ? `${t('fees.classShort')} ${className(s.school_class_id)}` : t('fees.allClasses') }}
                            <span class="block text-xs text-slate-500">{{ t('fees.dueOn') }} {{ s.due_on }}</span>
                        </span>
                        <span class="flex items-center gap-3">
                            <strong>{{ formatRupees(s.amount_paise) }}</strong>
                            <button type="button" class="text-xs text-red-700" @click="removeStructure(s)">✕</button>
                        </span>
                    </div>
                </div>

                <form class="grid grid-cols-2 gap-2 border-t border-slate-100 pt-3" @submit.prevent="addStructure">
                    <input v-model="structure.label" list="fee-labels" required maxlength="100" :placeholder="t('fees.periodPlaceholder')" class="col-span-2 rounded-lg border border-slate-300 px-2 py-1.5" />
                    <datalist id="fee-labels">
                        <option v-for="l in setup.labels" :key="l" :value="l" />
                    </datalist>
                    <select v-model="structure.feeHeadId" required class="rounded-lg border border-slate-300 px-2 py-1.5">
                        <option :value="null" disabled>{{ t('fees.chooseHead') }}</option>
                        <option v-for="h in activeHeads" :key="h.id" :value="h.id">{{ h.name }}</option>
                    </select>
                    <select v-model="structure.classId" class="rounded-lg border border-slate-300 px-2 py-1.5">
                        <option :value="null">{{ t('fees.allClasses') }}</option>
                        <option v-for="c in yearClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <label class="text-xs text-slate-600">
                        {{ t('fees.amount') }} (₹)
                        <input v-model="structure.amount" inputmode="decimal" required class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" />
                    </label>
                    <label class="text-xs text-slate-600">
                        {{ t('fees.dueOn') }}
                        <input v-model="structure.dueOn" type="date" required class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" />
                    </label>
                    <button type="submit" class="col-span-2 rounded-lg border border-blue-300 bg-blue-50 py-2 text-sm font-semibold text-blue-800">{{ t('fees.addStructure') }}</button>
                </form>
            </div>

            <!-- Numbering -->
            <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                <h2 class="font-semibold text-slate-800">{{ t('fees.numberingTitle') }}</h2>
                <p class="text-xs text-slate-500">{{ t('fees.numberingHint') }}</p>
                <ul class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs text-slate-600">
                    <li v-for="token in numberTokens" :key="token"><code class="font-mono text-slate-900">{{ token }}</code> {{ t(`fees.token_${token.replace(/[{}:\d]/g, '')}`) }}</li>
                </ul>
                <form v-for="type in ['invoice', 'receipt']" :key="type" class="space-y-2 rounded-xl bg-slate-50 p-3" @submit.prevent="saveNumbering(type)">
                    <p class="font-semibold text-slate-800">{{ t(`fees.numbering_${type}`) }}</p>
                    <input v-model="numbering[type].format" required maxlength="60" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 font-mono" />
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-xs text-slate-600">
                            {{ t('fees.resetCounter') }}
                            <select v-model="numbering[type].reset" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="academic_year">{{ t('fees.reset_academic_year') }}</option>
                                <option value="yearly">{{ t('fees.reset_yearly') }}</option>
                                <option value="never">{{ t('fees.reset_never') }}</option>
                            </select>
                        </label>
                        <label class="text-xs text-slate-600">
                            {{ t('fees.nextNumber') }}
                            <input v-model.number="numbering[type].next_number" type="number" min="1" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" />
                        </label>
                    </div>
                    <p class="text-sm">{{ t('fees.nextWillBe') }} <strong class="font-mono">{{ numbering[type].preview }}</strong></p>
                    <button type="submit" class="w-full rounded-lg border border-blue-300 bg-white py-1.5 text-sm font-semibold text-blue-800">{{ t('common.save') }}</button>
                </form>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useSchoolContext } from '@/composables/useSchoolContext';
import { formatRupees, toPaise } from '@/utils/money';

const { t } = useI18n();
const { activeSchoolId } = useSchoolContext();

const setup = ref(null);
const yearId = ref(null);
const error = ref('');
const message = ref('');
const newHead = ref('');
const structure = reactive({ label: '', feeHeadId: null, classId: null, amount: '', dueOn: '' });
const numbering = reactive({ invoice: {}, receipt: {} });
const numberTokens = ['{SEQ:4}', '{AY}', '{YYYY}', '{YY}', '{MM}', '{CODE}'];

const activeHeads = computed(() => setup.value?.heads.filter((h) => h.is_active) ?? []);
const yearClasses = computed(() => (setup.value?.classes ?? []).filter((c) => !c.academic_year_id || c.academic_year_id === yearId.value));
const structuresByLabel = computed(() => {
    const groups = {};
    for (const s of setup.value?.structures ?? []) {
        (groups[s.label] ??= []).push(s);
    }
    return groups;
});

const headName = (id) => setup.value.heads.find((h) => h.id === id)?.name ?? '—';
const className = (id) => setup.value.classes.find((c) => c.id === id)?.name ?? '—';

function fail(e) {
    message.value = '';
    error.value = e.response?.data?.message ?? t('common.error');
}

function done(text) {
    error.value = '';
    message.value = text;
}

async function load() {
    if (!activeSchoolId.value) return;
    try {
        const { data } = await axios.get('/api/fees/setup', {
            params: { school_id: activeSchoolId.value, academic_year_id: yearId.value || undefined },
        });
        setup.value = data;
        yearId.value = data.academic_year_id;
        Object.assign(numbering.invoice, data.numbering.invoice);
        Object.assign(numbering.receipt, data.numbering.receipt);
    } catch (e) {
        fail(e);
    }
}

async function addHead() {
    try {
        await axios.post('/api/fees/heads', { school_id: activeSchoolId.value, name: newHead.value });
        newHead.value = '';
        await load();
        done(t('ops.saved'));
    } catch (e) {
        fail(e);
    }
}

async function toggleHead(head) {
    try {
        await axios.put(`/api/fees/heads/${head.id}`, { is_active: !head.is_active });
        await load();
    } catch (e) {
        fail(e);
    }
}

async function addStructure() {
    const amountPaise = toPaise(structure.amount);
    if (!amountPaise) {
        error.value = t('fees.invalidAmount');
        return;
    }
    try {
        await axios.post('/api/fees/structures', {
            school_id: activeSchoolId.value,
            academic_year_id: yearId.value,
            school_class_id: structure.classId,
            fee_head_id: structure.feeHeadId,
            label: structure.label.trim(),
            amount_paise: amountPaise,
            due_on: structure.dueOn,
        });
        // Keep label and due date: the next line is usually for the same period.
        Object.assign(structure, { feeHeadId: null, classId: null, amount: '' });
        await load();
        done(t('ops.saved'));
    } catch (e) {
        fail(e);
    }
}

async function removeStructure(s) {
    if (!window.confirm(t('fees.confirmRemoveStructure'))) return;
    try {
        await axios.delete(`/api/fees/structures/${s.id}`);
        await load();
    } catch (e) {
        fail(e);
    }
}

async function saveNumbering(type) {
    try {
        const { data } = await axios.put('/api/fees/numbering', {
            school_id: activeSchoolId.value,
            type,
            format: numbering[type].format,
            reset: numbering[type].reset,
            next_number: numbering[type].next_number || undefined,
        });
        Object.assign(numbering[type], data.numbering);
        done(t('ops.saved'));
    } catch (e) {
        fail(e);
    }
}

watch(yearId, (next, prev) => {
    if (prev !== null && next !== prev) load();
});
watch(activeSchoolId, load);
onMounted(load);
</script>
