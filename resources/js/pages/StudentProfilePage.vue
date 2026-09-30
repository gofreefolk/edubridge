<template>
    <section class="space-y-4">
        <div v-if="loading && !student" class="text-center text-slate-600">{{ t('common.loading') }}</div>
        <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <template v-if="student">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm font-medium text-slate-500">{{ t('ops.profileTitle') }}</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ student.name }}</h1>
                <p class="text-slate-600">
                    {{ student.class || '—' }}{{ student.section ? ` · ${student.section}` : '' }}
                    · {{ student.admission_number || t('admin.noAdmission') }}
                </p>
            </div>

            <!-- Health & personal details -->
            <form class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4" @submit.prevent="saveProfile">
                <h2 class="font-semibold text-slate-800">{{ t('ops.profile') }}</h2>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <label class="block text-sm">
                        <span class="text-slate-600">{{ t('ops.dob') }}</span>
                        <input v-model="form.date_of_birth" type="date" :max="today" :disabled="!canEdit" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 disabled:bg-slate-50" />
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">{{ t('ops.gender') }}</span>
                        <select v-model="form.gender" :disabled="!canEdit" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 disabled:bg-slate-50">
                            <option :value="null">—</option>
                            <option value="male">{{ t('ops.male') }}</option>
                            <option value="female">{{ t('ops.female') }}</option>
                            <option value="other">{{ t('ops.otherGender') }}</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">{{ t('ops.bloodGroup') }}</span>
                        <select v-model="form.blood_group" :disabled="!canEdit" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 disabled:bg-slate-50">
                            <option :value="null">—</option>
                            <option v-for="bg in bloodGroups" :key="bg" :value="bg">{{ bg }}</option>
                        </select>
                    </label>
                </div>

                <label class="block text-sm">
                    <span class="font-medium text-red-800">{{ t('ops.allergies') }}</span>
                    <textarea v-model="form.allergies" rows="2" :disabled="!canEdit" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 disabled:bg-slate-50" />
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">{{ t('ops.medicalNotes') }}</span>
                    <textarea v-model="form.medical_notes" rows="2" :disabled="!canEdit" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 disabled:bg-slate-50" />
                </label>
                <label class="block text-sm">
                    <span class="text-slate-600">{{ t('ops.address') }}</span>
                    <textarea v-model="form.address" rows="2" :disabled="!canEdit" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 disabled:bg-slate-50" />
                </label>

                <button v-if="canEdit" type="submit" class="w-full rounded-xl bg-blue-700 py-2.5 font-semibold text-white disabled:opacity-60" :disabled="saving">
                    {{ saving ? t('common.loading') : t('ops.saveProfile') }}
                </button>
                <p v-else class="text-xs text-slate-500">{{ t('ops.readOnly') }}</p>
                <p v-if="savedMessage" class="text-sm text-green-700">{{ savedMessage }}</p>
            </form>

            <!-- Parents -->
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <h2 class="mb-2 font-semibold text-slate-800">{{ t('ops.parentsTitle') }}</h2>
                <ul v-if="student.parents.length" class="space-y-1 text-sm">
                    <li v-for="p in student.parents" :key="p.id">
                        {{ p.name }} · <a :href="`tel:${p.phone}`" class="text-blue-800">{{ p.phone }}</a>
                        <span class="text-slate-500"> · {{ p.relationship }}{{ p.is_primary ? ` · ${t('admin.primaryParent')}` : '' }}</span>
                    </li>
                </ul>
                <p v-else class="text-sm text-slate-500">{{ t('admin.noParents') }}</p>
            </div>

            <!-- Emergency contacts & authorised pickup -->
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <h2 class="mb-2 font-semibold text-slate-800">{{ t('ops.contactsTitle') }}</h2>
                <ul v-if="student.contacts.length" class="space-y-2">
                    <li v-for="c in student.contacts" :key="c.id" class="rounded-lg bg-slate-50 p-3 text-sm">
                        <p class="font-medium">{{ c.name }} <span class="text-slate-500">({{ c.relationship }})</span></p>
                        <a :href="`tel:${c.phone}`" class="text-blue-800">{{ c.phone }}</a>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <span v-if="c.is_emergency" class="rounded bg-red-100 px-2 py-0.5 text-xs text-red-800">{{ t('ops.emergency') }}</span>
                            <span v-if="c.can_pickup" class="rounded bg-green-100 px-2 py-0.5 text-xs text-green-800">{{ t('ops.canPickup') }}</span>
                        </div>
                        <p v-if="c.notes" class="mt-1 text-slate-500">{{ c.notes }}</p>
                        <button v-if="canEdit" type="button" class="mt-2 text-xs text-red-700" @click="removeContact(c)">{{ t('ops.remove') }}</button>
                    </li>
                </ul>
                <p v-else class="text-sm text-slate-500">{{ t('ops.noContacts') }}</p>

                <form v-if="canEdit" class="mt-3 space-y-2 rounded-lg border border-dashed border-slate-200 p-3" @submit.prevent="addContact">
                    <p class="text-sm font-medium text-slate-700">{{ t('ops.addContact') }}</p>
                    <input v-model="contactForm.name" required :placeholder="t('ops.contactName')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model="contactForm.relationship" required :placeholder="t('ops.relationship')" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <input v-model="contactForm.phone" type="tel" required :placeholder="t('ops.phone')" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <input v-model="contactForm.notes" :placeholder="t('ops.notes')" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <label class="flex items-center gap-2 text-sm"><input v-model="contactForm.is_emergency" type="checkbox" /> {{ t('ops.emergency') }}</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="contactForm.can_pickup" type="checkbox" /> {{ t('ops.canPickup') }}</label>
                    <button type="submit" class="w-full rounded-lg bg-slate-800 py-2 text-sm font-semibold text-white">{{ t('ops.addContact') }}</button>
                </form>
            </div>

            <router-link :to="{ name: 'centre-logs', query: { student_id: student.id } }" class="block rounded-2xl border border-blue-200 bg-blue-50 p-4 font-semibold text-blue-800">
                📓 {{ t('ops.logsTitle') }}
            </router-link>
        </template>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const props = defineProps({ id: { type: [String, Number], required: true } });
const { t } = useI18n();

const bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const today = new Date().toISOString().slice(0, 10);

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const savedMessage = ref('');
const student = ref(null);
const canEdit = ref(false);
const form = reactive({ date_of_birth: null, gender: null, blood_group: null, allergies: '', medical_notes: '', address: '' });
const emptyContact = () => ({ name: '', relationship: '', phone: '', notes: '', is_emergency: true, can_pickup: false });
const contactForm = ref(emptyContact());

function apply(data) {
    student.value = data.student;
    canEdit.value = data.can_edit;
    for (const key of Object.keys(form)) {
        form[key] = data.student[key] ?? (typeof form[key] === 'string' ? '' : null);
    }
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(`/api/students/${props.id}/profile`);
        apply(data);
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        loading.value = false;
    }
}

async function saveProfile() {
    saving.value = true;
    savedMessage.value = '';
    error.value = '';
    try {
        const { data } = await axios.put(`/api/students/${props.id}/profile`, {
            ...form,
            date_of_birth: form.date_of_birth || null,
        });
        apply(data);
        savedMessage.value = t('ops.saved');
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    } finally {
        saving.value = false;
    }
}

async function addContact() {
    error.value = '';
    try {
        const { data } = await axios.post(`/api/students/${props.id}/contacts`, contactForm.value);
        apply(data);
        contactForm.value = emptyContact();
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

async function removeContact(contact) {
    if (!window.confirm(t('ops.confirmRemoveContact'))) return;
    try {
        const { data } = await axios.delete(`/api/students/${props.id}/contacts/${contact.id}`);
        apply(data);
    } catch (e) {
        error.value = e.response?.data?.message ?? t('common.error');
    }
}

watch(() => props.id, load);
onMounted(load);
</script>
