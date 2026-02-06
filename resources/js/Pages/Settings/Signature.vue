<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import TipTapEditor from '@/Components/TipTapEditor.vue';
import { ref } from 'vue';

const props = defineProps({
    user: Object,
    branding: Object,
    signature: {
        type: String,
        default: '',
    },
    displayName: {
        type: String,
        default: '',
    },
});

const primaryColor = props.branding?.colors?.primary || '#3B82F6';
const displayName = ref(props.displayName);
const signatureHtml = ref(props.signature);
const isSaving = ref(false);
const saveError = ref('');
const saveSuccess = ref('');

const logout = () => {
    router.post('/logout');
};

const saveSignature = async () => {
    if (isSaving.value) return;

    isSaving.value = true;
    saveError.value = '';
    saveSuccess.value = '';

    try {
        const response = await fetch('/api/settings/signature', {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                signature_html: signatureHtml.value,
                display_name: displayName.value,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            saveSuccess.value = 'Assinatura salva com sucesso.';
            setTimeout(() => { saveSuccess.value = ''; }, 3000);
        } else {
            saveError.value = data.error || 'Falha ao salvar assinatura.';
        }
    } catch (e) {
        saveError.value = 'Erro de conexão.';
    } finally {
        isSaving.value = false;
    }
};

const clearSignature = () => {
    signatureHtml.value = '';
};
</script>

<template>
    <Head title="Assinatura" />

    <div class="min-h-screen flex flex-col bg-gray-100 p-4 gap-4">
        <!-- Header -->
        <header class="bg-white rounded-2xl shadow-lg flex-shrink-0 relative z-10">
            <div class="max-w-full mx-auto px-6 py-4 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <img
                        v-if="branding?.logo"
                        :src="branding.logo"
                        alt="Logo"
                        class="h-20 max-w-72 object-contain"
                    />
                    <h1 v-else class="text-xl font-bold" :style="{ color: primaryColor }">
                        Webmail
                    </h1>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-3">
                        <div
                            class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-medium shadow-sm"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            {{ user?.email?.charAt(0).toUpperCase() || '?' }}
                        </div>
                        <span class="text-sm text-gray-600 hidden sm:block">{{ user?.email }}</span>
                    </div>
                    <button
                        @click="logout"
                        class="text-sm text-gray-500 hover:text-gray-700 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors duration-200"
                    >
                        Sair
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col max-w-4xl mx-auto w-full gap-4">
            <!-- Toolbar Card -->
            <div class="bg-white rounded-2xl shadow-lg px-6 py-3 flex items-center space-x-2 flex-shrink-0">
                <Link
                    href="/mail/inbox"
                    class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-all duration-200 hover:scale-105"
                    title="Voltar"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>

                <div class="h-6 w-px bg-gray-200"></div>

                <h2 class="text-base font-semibold text-gray-800 flex-1">
                    Assinatura de e-mail
                </h2>

                <div class="flex items-center space-x-2">
                    <button
                        @click="clearSignature"
                        class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors"
                    >
                        Limpar
                    </button>
                    <button
                        @click="saveSignature"
                        :disabled="isSaving"
                        class="px-5 py-2 text-white text-sm font-semibold rounded-xl shadow-md hover:shadow-lg transition-all duration-200 disabled:opacity-50 flex items-center space-x-2"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        <span>{{ isSaving ? 'Salvando...' : 'Salvar' }}</span>
                    </button>
                </div>
            </div>

            <!-- Signature Editor Card -->
            <div class="flex-1 bg-white rounded-2xl shadow-lg overflow-hidden">
                <!-- Alertas -->
                <div v-if="saveError" class="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
                    {{ saveError }}
                </div>
                <div v-if="saveSuccess" class="mx-6 mt-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm">
                    {{ saveSuccess }}
                </div>

                <div class="p-6 space-y-4">
                    <!-- Nome de exibição -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome de exibição</label>
                        <input
                            v-model="displayName"
                            type="text"
                            placeholder="Ex: Rodrigo Bernardo"
                            maxlength="100"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:border-transparent transition-shadow"
                            :style="{ '--tw-ring-color': primaryColor + '40' }"
                        />
                        <p class="mt-1 text-xs text-gray-400">
                            Os destinatários verão: {{ displayName || user?.email }} &lt;{{ user?.email }}&gt;
                        </p>
                    </div>

                    <!-- Assinatura -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assinatura</label>
                        <p class="text-sm text-gray-500 mb-2">
                            Sua assinatura será adicionada automaticamente ao final de novos e-mails.
                        </p>
                    </div>

                    <TipTapEditor
                        v-model="signatureHtml"
                        placeholder="Escreva sua assinatura..."
                        min-height="200px"
                        :primary-color="primaryColor"
                    />

                    <!-- Preview -->
                    <div v-if="signatureHtml" class="mt-6">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Pré-visualização</h3>
                        <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                            <div class="prose prose-sm max-w-none" v-html="signatureHtml"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
