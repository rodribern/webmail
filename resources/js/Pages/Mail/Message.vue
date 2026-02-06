<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import FolderList from '@/Components/FolderList.vue';
import { ref, computed, onMounted, watch } from 'vue';

const props = defineProps({
    user: Object,
    branding: Object,
    folders: {
        type: Array,
        default: () => []
    },
    message: Object,
    currentFolder: {
        type: String,
        default: 'INBOX'
    },
    error: String
});

const primaryColor = props.branding?.colors?.primary || '#3B82F6';
const showMoveMenu = ref(false);
const isDeleting = ref(false);
const isMoving = ref(false);
const isTogglingRead = ref(false);
const isSeen = ref(props.message?.seen ?? true);
const emailIframe = ref(null);
const iframeHeight = ref(400);

const logout = () => {
    router.post('/logout');
};

const getBackUrl = () => {
    if (props.currentFolder === 'INBOX') {
        return '/mail/inbox';
    }
    return `/mail/folder/${encodeURIComponent(props.currentFolder)}`;
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatAddress = (addr) => {
    if (!addr) return '';
    if (Array.isArray(addr) && addr.length > 0) {
        addr = addr[0];
    }
    if (addr.name) {
        return `${addr.name} <${addr.email}>`;
    }
    return addr.email || '';
};

const formatAddressList = (list) => {
    if (!list || !Array.isArray(list)) return '';
    return list.map(a => formatAddress(a)).join(', ');
};

// Carrega o HTML no iframe de forma isolada
const loadIframeContent = () => {
    if (!emailIframe.value || !props.message?.body_html) return;

    const iframe = emailIframe.value;
    const doc = iframe.contentDocument || iframe.contentWindow.document;

    // HTML base com reset de estilos e conteúdo do email
    const htmlContent = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 16px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #1f2937;
            background: white;
        }
        img {
            max-width: 100%;
            height: auto;
        }
        a {
            color: ${primaryColor};
        }
        /* Preserva estilos de tabela de emails */
        table {
            border-collapse: collapse;
        }
    </style>
</head>
<body>${props.message.body_html}</body>
</html>`;

    doc.open();
    doc.write(htmlContent);
    doc.close();

    // Ajusta altura do iframe baseado no conteúdo
    setTimeout(() => {
        try {
            const height = doc.body.scrollHeight;
            iframeHeight.value = Math.max(height + 32, 200);
        } catch (e) {
            iframeHeight.value = 600;
        }
    }, 100);

    // Observer para ajustes dinâmicos (imagens carregando, etc)
    setTimeout(() => {
        try {
            const height = doc.body.scrollHeight;
            iframeHeight.value = Math.max(height + 32, 200);
        } catch (e) {}
    }, 500);
};

onMounted(() => {
    if (props.message?.body_html) {
        setTimeout(loadIframeContent, 50);
    }
});

watch(() => props.message, () => {
    if (props.message?.body_html) {
        setTimeout(loadIframeContent, 50);
    }
});

const deleteMessage = async () => {
    if (isDeleting.value) return;

    if (!confirm('Tem certeza que deseja excluir esta mensagem?')) {
        return;
    }

    isDeleting.value = true;
    try {
        const response = await fetch(`/api/mail/message/${encodeURIComponent(props.currentFolder)}/${props.message.uid}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            }
        });

        if (response.ok) {
            router.visit(getBackUrl());
        } else {
            alert('Erro ao excluir mensagem');
        }
    } catch (e) {
        alert('Erro ao excluir mensagem');
    } finally {
        isDeleting.value = false;
    }
};

const moveMessage = async (targetFolder) => {
    if (isMoving.value) return;

    isMoving.value = true;
    showMoveMenu.value = false;

    try {
        const response = await fetch(`/api/mail/message/${encodeURIComponent(props.currentFolder)}/${props.message.uid}/move`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ target_folder: targetFolder })
        });

        if (response.ok) {
            router.visit(getBackUrl());
        } else {
            alert('Erro ao mover mensagem');
        }
    } catch (e) {
        alert('Erro ao mover mensagem');
    } finally {
        isMoving.value = false;
    }
};

const toggleMoveMenu = () => {
    showMoveMenu.value = !showMoveMenu.value;
};

const toggleSeen = async () => {
    if (isTogglingRead.value) return;
    isTogglingRead.value = true;

    const newSeen = !isSeen.value;
    try {
        const response = await fetch(`/api/mail/message/${encodeURIComponent(props.currentFolder)}/${props.message.uid}/seen`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ seen: newSeen })
        });

        if (response.ok) {
            isSeen.value = newSeen;
        } else {
            alert('Erro ao alterar status de leitura');
        }
    } catch (e) {
        alert('Erro ao alterar status de leitura');
    } finally {
        isTogglingRead.value = false;
    }
};

const getFolderDisplayName = (path) => {
    const names = {
        'INBOX': 'Caixa de Entrada',
        'Sent': 'Enviados',
        'Drafts': 'Rascunhos',
        'Trash': 'Lixeira',
        'Spam': 'Spam',
        'Junk': 'Lixo Eletrônico',
    };
    return names[path] || path;
};

// Filtra pastas para o menu de mover (exclui a pasta atual)
const moveableFolders = computed(() => {
    return props.folders.filter(f => f.path !== props.currentFolder);
});
</script>

<template>
    <Head :title="message?.subject || 'Mensagem'" />

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
                    <!-- Admin link -->
                    <a
                        v-if="user?.is_admin"
                        href="/admin/branding"
                        class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors duration-200"
                        title="Personalização do domínio"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </a>

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
        <div class="flex-1 flex overflow-hidden gap-4">
            <!-- Sidebar Column -->
            <div class="w-72 flex-shrink-0 flex flex-col gap-4">
                <!-- Compose Button Card -->
                <div class="bg-white rounded-2xl shadow-lg px-4 py-3">
                    <Link
                        href="/mail/compose"
                        class="w-full py-2 text-white rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center space-x-2"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Escrever</span>
                    </Link>
                </div>

                <!-- Folder List Card -->
                <div class="flex-1 bg-white rounded-2xl shadow-lg">
                    <div class="flex-1 overflow-y-auto py-3">
                        <FolderList
                            :folders="folders"
                            :current-folder="currentFolder"
                            :primary-color="primaryColor"
                        />
                    </div>
                </div>
            </div>

            <!-- Message Column -->
            <div class="flex-1 flex flex-col gap-4 overflow-hidden">
                <!-- Toolbar Card -->
                <div class="bg-white rounded-2xl shadow-lg px-6 py-3 flex items-center space-x-2 flex-shrink-0">
                    <Link
                        :href="getBackUrl()"
                        class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-all duration-200 hover:scale-105"
                        title="Voltar"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>

                    <div class="h-6 w-px bg-gray-200"></div>

                    <!-- Reply -->
                    <Link
                        v-if="message"
                        :href="`/mail/compose?mode=reply&folder=${encodeURIComponent(currentFolder)}&uid=${message.uid}`"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                        </svg>
                        <span>Responder</span>
                    </Link>

                    <!-- Reply All -->
                    <Link
                        v-if="message"
                        :href="`/mail/compose?mode=reply_all&folder=${encodeURIComponent(currentFolder)}&uid=${message.uid}`"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 15l-6-6m0 0l6-6" />
                        </svg>
                        <span>Responder a todos</span>
                    </Link>

                    <!-- Forward -->
                    <Link
                        v-if="message"
                        :href="`/mail/compose?mode=forward&folder=${encodeURIComponent(currentFolder)}&uid=${message.uid}`"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 15l6-6m0 0l-6-6m6 6H9a6 6 0 000 12h3" />
                        </svg>
                        <span>Encaminhar</span>
                    </Link>

                    <!-- Toggle Read/Unread -->
                    <button
                        v-if="message"
                        @click="toggleSeen"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                        :disabled="isTogglingRead"
                    >
                        <!-- Envelope open (mark as unread) -->
                        <svg v-if="isSeen" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 19V8l9-5 9 5v11a1 1 0 01-1 1H4a1 1 0 01-1-1z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6" />
                        </svg>
                        <!-- Envelope closed (mark as read) -->
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>{{ isSeen ? 'Marcar como não lida' : 'Marcar como lida' }}</span>
                    </button>

                    <div class="h-6 w-px bg-gray-200"></div>

                    <!-- Move button with dropdown -->
                    <div class="relative">
                        <button
                            @click="toggleMoveMenu"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                            :disabled="isMoving"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <span>Mover</span>
                        </button>

                        <!-- Dropdown menu -->
                        <div
                            v-if="showMoveMenu"
                            class="absolute left-0 mt-1 w-48 bg-white rounded-xl shadow-lg border z-10 py-1 overflow-hidden"
                        >
                            <button
                                v-for="folder in moveableFolders"
                                :key="folder.path"
                                @click="moveMessage(folder.path)"
                                class="w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-50 transition-colors"
                            >
                                {{ getFolderDisplayName(folder.name) }}
                            </button>
                        </div>
                    </div>

                    <button
                        @click="deleteMessage"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                        :disabled="isDeleting"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Excluir</span>
                    </button>
                </div>

                <!-- Message Content Card -->
                <main class="flex-1 flex flex-col overflow-hidden bg-white rounded-2xl shadow-lg">

                <!-- Error Alert -->
                <div v-if="error" class="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm flex items-center shadow-sm">
                    <svg class="h-5 w-5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    {{ error }}
                </div>

                <!-- Message Content -->
                <div v-if="message" class="flex-1 overflow-y-auto p-6">
                    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                        <!-- Message Header -->
                        <div class="px-6 py-5 border-b bg-gray-50/50">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                                {{ message.subject || '(Sem assunto)' }}
                            </h2>

                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-3">
                                    <!-- Avatar -->
                                    <div
                                        class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0 shadow-sm"
                                        :style="{ backgroundColor: primaryColor }"
                                    >
                                        {{ (formatAddress(message.from)?.charAt(0) || '?').toUpperCase() }}
                                    </div>

                                    <div class="space-y-0.5">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ formatAddress(message.from) }}
                                        </div>
                                        <div v-if="message.to?.length" class="text-xs text-gray-500">
                                            Para: {{ formatAddressList(message.to) }}
                                        </div>
                                        <div v-if="message.cc?.length" class="text-xs text-gray-500">
                                            Cc: {{ formatAddressList(message.cc) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="text-sm text-gray-500">
                                    {{ formatDate(message.date) }}
                                </div>
                            </div>
                        </div>

                        <!-- Attachments -->
                        <div v-if="message.attachments?.length" class="px-6 py-3 border-b bg-amber-50/50">
                            <div class="flex items-center text-sm text-amber-700 mb-2">
                                <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                {{ message.attachments.length }} anexo(s)
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a
                                    v-for="(attachment, index) in message.attachments"
                                    :key="index"
                                    :href="`/api/mail/attachment/${encodeURIComponent(currentFolder)}/${message.uid}/${index}`"
                                    target="_blank"
                                    class="flex items-center px-3 py-2 bg-white border border-amber-200 rounded-lg text-sm hover:bg-amber-50 transition-colors group"
                                >
                                    <svg class="h-4 w-4 text-amber-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="text-gray-700 group-hover:text-gray-900">{{ attachment.name }}</span>
                                    <span class="text-gray-400 ml-2 text-xs">({{ attachment.size_human }})</span>
                                </a>
                            </div>
                        </div>

                        <!-- Message Body -->
                        <div class="min-h-[200px]">
                            <!-- HTML via iframe isolado -->
                            <iframe
                                v-if="message.body_html"
                                ref="emailIframe"
                                class="w-full border-0"
                                :style="{ height: iframeHeight + 'px' }"
                                sandbox="allow-same-origin"
                            ></iframe>

                            <!-- Texto puro -->
                            <pre
                                v-else-if="message.body_text"
                                class="whitespace-pre-wrap font-sans text-sm text-gray-700 p-6"
                            >{{ message.body_text }}</pre>

                            <!-- Sem conteúdo -->
                            <div v-else class="p-6 text-gray-500 italic text-center">
                                Esta mensagem não possui conteúdo.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No message state -->
                <div v-else class="flex-1 flex items-center justify-center">
                    <div class="text-center text-gray-500">
                        <div class="mx-auto w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                            <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <p class="font-medium">Mensagem não encontrada.</p>
                        <Link
                            :href="getBackUrl()"
                            class="mt-4 inline-block text-sm hover:underline"
                            :style="{ color: primaryColor }"
                        >
                            Voltar para {{ getFolderDisplayName(currentFolder) }}
                        </Link>
                    </div>
                </div>
            </main>
            </div>
        </div>
    </div>
</template>
