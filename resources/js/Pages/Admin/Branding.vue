<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';

const props = defineProps({
    user: Object,
    branding: Object,
    domain: Object,
    currentBranding: Object,
});

// Estado do formulário
const form = reactive({
    primary_color: props.currentBranding.primary_color,
    secondary_color: props.currentBranding.secondary_color,
    background_color: props.currentBranding.background_color,
    sidebar_color: props.currentBranding.sidebar_color,
    custom_css: props.currentBranding.custom_css || '',
});

// Estado de imagens
const logo = ref(props.currentBranding.logo);
const favicon = ref(props.currentBranding.favicon);

// Estados de UI
const saving = ref(false);
const uploadingLogo = ref(false);
const uploadingFavicon = ref(false);
const message = ref(null);
const messageType = ref('success');

// Cores para o preview
const previewColors = computed(() => ({
    primary: form.primary_color,
    secondary: form.secondary_color,
    background: form.background_color,
    sidebar: form.sidebar_color,
}));

// Funções
const logout = () => {
    router.post('/logout');
};

const showMessage = (text, type = 'success') => {
    message.value = text;
    messageType.value = type;
    setTimeout(() => {
        message.value = null;
    }, 5000);
};

const saveColors = async () => {
    saving.value = true;
    message.value = null;

    try {
        const response = await fetch('/admin/branding', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify(form),
        });

        const data = await response.json();

        if (response.ok) {
            showMessage(data.message || 'Configurações salvas com sucesso.');
        } else {
            const errors = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
            showMessage(errors || 'Erro ao salvar configurações.', 'error');
        }
    } catch (error) {
        showMessage('Erro ao salvar configurações.', 'error');
    } finally {
        saving.value = false;
    }
};

const uploadLogo = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    uploadingLogo.value = true;
    message.value = null;

    const formData = new FormData();
    formData.append('logo', file);

    try {
        const response = await fetch('/admin/branding/logo', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        });

        const data = await response.json();

        if (response.ok) {
            logo.value = data.logo;
            showMessage(data.message || 'Logo enviado com sucesso.');
        } else {
            const errors = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
            showMessage(errors || 'Erro ao enviar logo.', 'error');
        }
    } catch (error) {
        showMessage('Erro ao enviar logo.', 'error');
    } finally {
        uploadingLogo.value = false;
        event.target.value = '';
    }
};

const removeLogo = async () => {
    if (!confirm('Tem certeza que deseja remover o logo?')) return;

    try {
        const response = await fetch('/admin/branding/logo', {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (response.ok) {
            logo.value = null;
            showMessage(data.message || 'Logo removido com sucesso.');
        } else {
            showMessage(data.message || 'Erro ao remover logo.', 'error');
        }
    } catch (error) {
        showMessage('Erro ao remover logo.', 'error');
    }
};

const uploadFavicon = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    uploadingFavicon.value = true;
    message.value = null;

    const formData = new FormData();
    formData.append('favicon', file);

    try {
        const response = await fetch('/admin/branding/favicon', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        });

        const data = await response.json();

        if (response.ok) {
            favicon.value = data.favicon;
            showMessage(data.message || 'Favicon enviado com sucesso.');
        } else {
            const errors = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
            showMessage(errors || 'Erro ao enviar favicon.', 'error');
        }
    } catch (error) {
        showMessage('Erro ao enviar favicon.', 'error');
    } finally {
        uploadingFavicon.value = false;
        event.target.value = '';
    }
};

const removeFavicon = async () => {
    if (!confirm('Tem certeza que deseja remover o favicon?')) return;

    try {
        const response = await fetch('/admin/branding/favicon', {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (response.ok) {
            favicon.value = null;
            showMessage(data.message || 'Favicon removido com sucesso.');
        } else {
            showMessage(data.message || 'Erro ao remover favicon.', 'error');
        }
    } catch (error) {
        showMessage('Erro ao remover favicon.', 'error');
    }
};

const resetColors = () => {
    form.primary_color = '#3B82F6';
    form.secondary_color = '#1E40AF';
    form.background_color = '#F9FAFB';
    form.sidebar_color = '#FFFFFF';
};
</script>

<template>
    <Head title="Personalização do Domínio" />

    <div class="min-h-screen flex flex-col bg-gray-100 p-4 gap-4">
        <!-- Header -->
        <header class="bg-white rounded-2xl shadow-lg flex-shrink-0">
            <div class="max-w-full mx-auto px-6 py-4 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a
                        href="/mail/inbox"
                        class="flex items-center space-x-2 text-gray-500 hover:text-gray-700 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span class="text-sm">Voltar ao Webmail</span>
                    </a>
                    <div class="h-6 w-px bg-gray-200"></div>
                    <h1 class="text-xl font-bold" :style="{ color: previewColors.primary }">
                        Personalização
                    </h1>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-500">
                        Domínio: <span class="font-medium text-gray-700">{{ domain.name }}</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div
                            class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-medium shadow-sm"
                            :style="{ backgroundColor: previewColors.primary }"
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

        <!-- Mensagem de feedback -->
        <div
            v-if="message"
            class="rounded-xl px-4 py-3 shadow-sm flex items-center justify-between"
            :class="messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'"
        >
            <div class="flex items-center space-x-2">
                <svg v-if="messageType === 'success'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{{ message }}</span>
            </div>
            <button @click="message = null" class="text-current opacity-70 hover:opacity-100">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex gap-4 overflow-hidden">
            <!-- Coluna de Configurações -->
            <div class="w-96 flex-shrink-0 flex flex-col gap-4 overflow-y-auto">
                <!-- Logo -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Logo</h2>

                    <div class="space-y-4">
                        <!-- Preview do logo atual -->
                        <div class="flex items-center justify-center p-4 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 min-h-24">
                            <img
                                v-if="logo"
                                :src="logo"
                                alt="Logo atual"
                                class="max-h-16 max-w-full object-contain"
                            />
                            <span v-else class="text-sm text-gray-400">Nenhum logo definido</span>
                        </div>

                        <!-- Botões -->
                        <div class="flex gap-2">
                            <label
                                class="flex-1 cursor-pointer py-2 px-4 text-center text-sm font-medium rounded-xl border-2 border-dashed transition-all"
                                :class="uploadingLogo ? 'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed' : 'border-blue-300 text-blue-600 hover:bg-blue-50 hover:border-blue-400'"
                            >
                                <input
                                    type="file"
                                    accept=".png,.jpg,.jpeg,.svg,.webp"
                                    class="hidden"
                                    @change="uploadLogo"
                                    :disabled="uploadingLogo"
                                />
                                {{ uploadingLogo ? 'Enviando...' : 'Enviar Logo' }}
                            </label>
                            <button
                                v-if="logo"
                                @click="removeLogo"
                                class="py-2 px-4 text-sm font-medium text-red-600 border-2 border-red-200 rounded-xl hover:bg-red-50 transition-colors"
                            >
                                Remover
                            </button>
                        </div>

                        <p class="text-xs text-gray-500">
                            PNG, JPG, SVG ou WebP. Máximo 2MB. Recomendado: 400x100px.
                        </p>
                    </div>
                </div>

                <!-- Favicon -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Favicon</h2>

                    <div class="space-y-4">
                        <!-- Preview do favicon atual -->
                        <div class="flex items-center justify-center p-4 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
                            <img
                                v-if="favicon"
                                :src="favicon"
                                alt="Favicon atual"
                                class="w-8 h-8 object-contain"
                            />
                            <span v-else class="text-sm text-gray-400">Nenhum favicon definido</span>
                        </div>

                        <!-- Botões -->
                        <div class="flex gap-2">
                            <label
                                class="flex-1 cursor-pointer py-2 px-4 text-center text-sm font-medium rounded-xl border-2 border-dashed transition-all"
                                :class="uploadingFavicon ? 'bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed' : 'border-blue-300 text-blue-600 hover:bg-blue-50 hover:border-blue-400'"
                            >
                                <input
                                    type="file"
                                    accept=".ico,.png"
                                    class="hidden"
                                    @change="uploadFavicon"
                                    :disabled="uploadingFavicon"
                                />
                                {{ uploadingFavicon ? 'Enviando...' : 'Enviar Favicon' }}
                            </label>
                            <button
                                v-if="favicon"
                                @click="removeFavicon"
                                class="py-2 px-4 text-sm font-medium text-red-600 border-2 border-red-200 rounded-xl hover:bg-red-50 transition-colors"
                            >
                                Remover
                            </button>
                        </div>

                        <p class="text-xs text-gray-500">
                            ICO ou PNG. Máximo 512KB. Recomendado: 32x32px.
                        </p>
                    </div>
                </div>

                <!-- Cores -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">Cores</h2>
                        <button
                            @click="resetColors"
                            class="text-xs text-gray-500 hover:text-gray-700 underline"
                        >
                            Restaurar padrão
                        </button>
                    </div>

                    <div class="space-y-4">
                        <!-- Cor Primária -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Cor Primária
                            </label>
                            <div class="flex items-center gap-3">
                                <input
                                    type="color"
                                    v-model="form.primary_color"
                                    class="w-12 h-10 rounded-lg border border-gray-200 cursor-pointer"
                                />
                                <input
                                    type="text"
                                    v-model="form.primary_color"
                                    class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg font-mono uppercase"
                                    maxlength="7"
                                />
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Botões, links, destaques</p>
                        </div>

                        <!-- Cor Secundária -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Cor Secundária
                            </label>
                            <div class="flex items-center gap-3">
                                <input
                                    type="color"
                                    v-model="form.secondary_color"
                                    class="w-12 h-10 rounded-lg border border-gray-200 cursor-pointer"
                                />
                                <input
                                    type="text"
                                    v-model="form.secondary_color"
                                    class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg font-mono uppercase"
                                    maxlength="7"
                                />
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Hover, elementos secundários</p>
                        </div>

                        <!-- Cor de Fundo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Cor de Fundo
                            </label>
                            <div class="flex items-center gap-3">
                                <input
                                    type="color"
                                    v-model="form.background_color"
                                    class="w-12 h-10 rounded-lg border border-gray-200 cursor-pointer"
                                />
                                <input
                                    type="text"
                                    v-model="form.background_color"
                                    class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg font-mono uppercase"
                                    maxlength="7"
                                />
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Fundo geral da página</p>
                        </div>

                        <!-- Cor da Sidebar -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Cor da Barra Lateral
                            </label>
                            <div class="flex items-center gap-3">
                                <input
                                    type="color"
                                    v-model="form.sidebar_color"
                                    class="w-12 h-10 rounded-lg border border-gray-200 cursor-pointer"
                                />
                                <input
                                    type="text"
                                    v-model="form.sidebar_color"
                                    class="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg font-mono uppercase"
                                    maxlength="7"
                                />
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Fundo da barra lateral</p>
                        </div>
                    </div>
                </div>

                <!-- CSS Customizado -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">CSS Customizado</h2>

                    <div class="space-y-3">
                        <textarea
                            v-model="form.custom_css"
                            rows="6"
                            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl font-mono resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="/* Seus estilos CSS aqui */&#10;.custom-class {&#10;    color: red;&#10;}"
                        ></textarea>
                        <p class="text-xs text-gray-500">
                            Máximo 10.000 caracteres. Não são permitidos @import ou URLs externas.
                        </p>
                    </div>
                </div>

                <!-- Botão Salvar -->
                <button
                    @click="saveColors"
                    :disabled="saving"
                    class="w-full py-3 text-white rounded-xl text-sm font-semibold shadow-lg hover:shadow-xl transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                    :style="{ backgroundColor: previewColors.primary }"
                >
                    {{ saving ? 'Salvando...' : 'Salvar Alterações' }}
                </button>
            </div>

            <!-- Coluna de Preview -->
            <div class="flex-1 bg-white rounded-2xl shadow-lg p-6 overflow-hidden flex flex-col">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Preview</h2>

                <!-- Preview Container -->
                <div
                    class="flex-1 rounded-xl overflow-hidden border border-gray-200"
                    :style="{ backgroundColor: previewColors.background }"
                >
                    <div class="h-full flex flex-col p-3 gap-3">
                        <!-- Preview Header -->
                        <div class="bg-white rounded-xl shadow-sm px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <img
                                    v-if="logo"
                                    :src="logo"
                                    alt="Logo"
                                    class="h-6 max-w-32 object-contain"
                                />
                                <span v-else class="font-bold" :style="{ color: previewColors.primary }">
                                    Webmail
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div
                                    class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs"
                                    :style="{ backgroundColor: previewColors.primary }"
                                >
                                    U
                                </div>
                                <span class="text-xs text-gray-500">usuario@{{ domain.name }}</span>
                            </div>
                        </div>

                        <!-- Preview Content -->
                        <div class="flex-1 flex gap-3 min-h-0">
                            <!-- Preview Sidebar -->
                            <div
                                class="w-44 rounded-xl shadow-sm p-3 flex flex-col gap-2"
                                :style="{ backgroundColor: previewColors.sidebar }"
                            >
                                <button
                                    class="w-full py-1.5 text-white rounded-lg text-xs font-medium"
                                    :style="{ backgroundColor: previewColors.primary }"
                                >
                                    Escrever
                                </button>

                                <div class="space-y-1 mt-2">
                                    <div
                                        class="px-2 py-1.5 rounded-lg text-xs font-medium text-white"
                                        :style="{ backgroundColor: previewColors.primary }"
                                    >
                                        Caixa de Entrada
                                    </div>
                                    <div class="px-2 py-1.5 rounded-lg text-xs text-gray-600 hover:bg-gray-100">
                                        Enviados
                                    </div>
                                    <div class="px-2 py-1.5 rounded-lg text-xs text-gray-600 hover:bg-gray-100">
                                        Rascunhos
                                    </div>
                                    <div class="px-2 py-1.5 rounded-lg text-xs text-gray-600 hover:bg-gray-100">
                                        Lixeira
                                    </div>
                                </div>
                            </div>

                            <!-- Preview Message List -->
                            <div class="flex-1 bg-white rounded-xl shadow-sm p-3 flex flex-col gap-2">
                                <div class="text-xs font-medium text-gray-700 pb-2 border-b">
                                    Caixa de Entrada
                                </div>

                                <!-- Mensagem exemplo -->
                                <div class="flex items-start gap-2 p-2 rounded-lg hover:bg-gray-50">
                                    <div
                                        class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs flex-shrink-0"
                                        :style="{ backgroundColor: previewColors.secondary }"
                                    >
                                        J
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-medium text-gray-800 truncate">João Silva</span>
                                            <span class="text-[10px] text-gray-400">10:30</span>
                                        </div>
                                        <div class="text-xs text-gray-600 truncate">Reunião amanhã</div>
                                        <div class="text-[10px] text-gray-400 truncate">Olá, gostaria de confirmar...</div>
                                    </div>
                                </div>

                                <div class="flex items-start gap-2 p-2 rounded-lg bg-blue-50/50">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs flex-shrink-0 bg-green-500">
                                        M
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-800 truncate">Maria Santos</span>
                                            <span class="text-[10px] text-gray-400">Ontem</span>
                                        </div>
                                        <div class="text-xs font-medium text-gray-700 truncate">Proposta comercial</div>
                                        <div class="text-[10px] text-gray-400 truncate">Segue em anexo a proposta...</div>
                                    </div>
                                </div>

                                <div class="flex items-start gap-2 p-2 rounded-lg hover:bg-gray-50">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs flex-shrink-0 bg-purple-500">
                                        P
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-medium text-gray-800 truncate">Pedro Oliveira</span>
                                            <span class="text-[10px] text-gray-400">02/02</span>
                                        </div>
                                        <div class="text-xs text-gray-600 truncate">Re: Orçamento</div>
                                        <div class="text-[10px] text-gray-400 truncate">Aprovado! Podemos prosseguir...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-gray-500 mt-3 text-center">
                    O preview mostra como o webmail ficará com as cores selecionadas.
                </p>
            </div>
        </div>
    </div>
</template>
