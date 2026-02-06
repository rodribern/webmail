<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import FolderList from '@/Components/FolderList.vue';
import MessageList from '@/Components/MessageList.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    user: Object,
    branding: Object,
    folders: {
        type: Array,
        default: () => []
    },
    messages: {
        type: Array,
        default: () => []
    },
    pagination: {
        type: Object,
        default: () => ({ total: 0, page: 1, per_page: 50, total_pages: 1 })
    },
    currentFolder: {
        type: String,
        default: 'INBOX'
    },
    error: String
});

const primaryColor = props.branding?.colors?.primary || '#3B82F6';
const searchQuery = ref('');
const searchResults = ref(null);
const isSearching = ref(false);
const selectedUids = ref(new Set());
const showActionsMenu = ref(false);
const showMoveSubmenu = ref(false);
const isBatchProcessing = ref(false);

const logout = () => {
    router.post('/logout');
};

const refresh = () => {
    if (searchResults.value) {
        clearSearch();
        return;
    }
    router.reload();
};

const performSearch = async () => {
    const q = searchQuery.value.trim();
    if (!q) {
        clearSearch();
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(`/api/mail/messages/${encodeURIComponent(props.currentFolder)}/search?q=${encodeURIComponent(q)}`, {
            headers: { 'Accept': 'application/json' },
        });
        if (response.ok) {
            searchResults.value = await response.json();
        }
    } catch (e) {
        // Silencioso
    } finally {
        isSearching.value = false;
    }
};

const clearSearch = () => {
    searchQuery.value = '';
    searchResults.value = null;
};

const currentMessages = () => {
    return searchResults.value ? searchResults.value.messages : props.messages;
};

const toggleSelect = (uid) => {
    const newSet = new Set(selectedUids.value);
    if (newSet.has(uid)) {
        newSet.delete(uid);
    } else {
        newSet.add(uid);
    }
    selectedUids.value = newSet;
};

const toggleSelectAll = () => {
    const msgs = currentMessages();
    const allSelected = msgs.length > 0 && msgs.every(m => selectedUids.value.has(m.uid));
    if (allSelected) {
        selectedUids.value = new Set();
    } else {
        selectedUids.value = new Set(msgs.map(m => m.uid));
    }
};

const batchMarkSeen = async (seen) => {
    if (selectedUids.value.size === 0 || isBatchProcessing.value) return;
    isBatchProcessing.value = true;
    showActionsMenu.value = false;

    try {
        const response = await fetch(`/api/mail/messages/${encodeURIComponent(props.currentFolder)}/batch-seen`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                uids: Array.from(selectedUids.value),
                seen: seen,
            })
        });

        if (response.ok) {
            selectedUids.value = new Set();
            router.reload();
        } else {
            const data = await response.json().catch(() => ({}));
            alert(data.error || data.message || `Erro ao alterar status de leitura (HTTP ${response.status})`);
        }
    } catch (e) {
        alert('Erro de conexão ao alterar status de leitura');
    } finally {
        isBatchProcessing.value = false;
    }
};

const batchDeleteMessages = async () => {
    if (selectedUids.value.size === 0 || isBatchProcessing.value) return;

    const count = selectedUids.value.size;
    if (!confirm(`Tem certeza que deseja excluir ${count} ${count === 1 ? 'mensagem' : 'mensagens'}?`)) {
        return;
    }

    isBatchProcessing.value = true;
    showActionsMenu.value = false;

    try {
        const response = await fetch(`/api/mail/messages/${encodeURIComponent(props.currentFolder)}/batch-delete`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                uids: Array.from(selectedUids.value),
            })
        });

        if (response.ok) {
            selectedUids.value = new Set();
            router.reload();
        } else {
            alert('Erro ao excluir mensagens');
        }
    } catch (e) {
        alert('Erro ao excluir mensagens');
    } finally {
        isBatchProcessing.value = false;
    }
};

const singleSelectedUid = computed(() => {
    if (selectedUids.value.size === 1) {
        return Array.from(selectedUids.value)[0];
    }
    return null;
});

const getComposeUrl = (mode) => {
    const uid = singleSelectedUid.value;
    if (!uid) return '#';
    return `/mail/compose?mode=${mode}&folder=${encodeURIComponent(props.currentFolder)}&uid=${uid}`;
};

const closeActionsMenu = () => {
    showActionsMenu.value = false;
    showMoveSubmenu.value = false;
};

const moveTargetFolders = computed(() => {
    return props.folders.filter(f => f.path !== props.currentFolder);
});

const batchMoveMessages = async (targetFolder) => {
    if (selectedUids.value.size === 0 || isBatchProcessing.value) return;

    isBatchProcessing.value = true;
    showActionsMenu.value = false;
    showMoveSubmenu.value = false;

    try {
        const response = await fetch(`/api/mail/messages/${encodeURIComponent(props.currentFolder)}/batch-move`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                uids: Array.from(selectedUids.value),
                target: targetFolder,
            })
        });

        if (response.ok) {
            selectedUids.value = new Set();
            router.reload();
        } else {
            alert('Erro ao mover mensagens');
        }
    } catch (e) {
        alert('Erro ao mover mensagens');
    } finally {
        isBatchProcessing.value = false;
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

const getCurrentUrl = () => {
    if (props.currentFolder === 'INBOX') {
        return '/mail/inbox';
    }
    return `/mail/folder/${encodeURIComponent(props.currentFolder)}`;
};
</script>

<template>
    <Head :title="getFolderDisplayName(currentFolder)" />

    <div class="min-h-screen flex flex-col bg-gray-100 p-4 gap-4" @click="closeActionsMenu">
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

                    <!-- Assinatura link -->
                    <a
                        href="/settings/signature"
                        class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors duration-200"
                        title="Assinatura de e-mail"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </a>

                    <!-- User info -->
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
                <div class="bg-white rounded-2xl shadow-lg px-6 py-3 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center space-x-2">
                        <button
                            @click="refresh"
                            class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-all duration-200 hover:scale-105 active:scale-95"
                            :title="searchResults ? 'Limpar busca' : 'Atualizar'"
                        >
                            <svg v-if="!searchResults" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <!-- Actions dropdown -->
                        <div class="relative" @click.stop>
                            <button
                                @click="showActionsMenu = !showActionsMenu"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg transition-all duration-200"
                                :class="selectedUids.size > 0
                                    ? 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'
                                    : 'text-gray-400 cursor-not-allowed'"
                                :disabled="selectedUids.size === 0 || isBatchProcessing"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                </svg>
                                <span>Ações</span>
                                <span v-if="selectedUids.size > 0" class="text-xs font-semibold bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded-full">
                                    {{ selectedUids.size }}
                                </span>
                            </button>

                            <!-- Dropdown menu -->
                            <div
                                v-if="showActionsMenu && selectedUids.size > 0"
                                class="absolute left-0 mt-1 w-56 bg-white rounded-xl shadow-lg border z-20 py-1"
                            >
                                <!-- Single-message actions -->
                                <template v-if="singleSelectedUid">
                                    <a
                                        :href="getComposeUrl('reply')"
                                        class="w-full px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2"
                                    >
                                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                        </svg>
                                        Responder
                                    </a>
                                    <a
                                        :href="getComposeUrl('reply_all')"
                                        class="w-full px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2"
                                    >
                                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 15l-6-6m0 0l6-6" />
                                        </svg>
                                        Responder a todos
                                    </a>
                                    <a
                                        :href="getComposeUrl('forward')"
                                        class="w-full px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2"
                                    >
                                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 15l6-6m0 0l-6-6m6 6H9a6 6 0 000 12h3" />
                                        </svg>
                                        Encaminhar
                                    </a>
                                    <div class="border-t border-gray-100 my-1"></div>
                                </template>

                                <button
                                    @click="batchMarkSeen(true)"
                                    class="w-full px-4 py-2.5 text-sm text-left text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2"
                                >
                                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    Marcar como lida
                                </button>
                                <button
                                    @click="batchMarkSeen(false)"
                                    class="w-full px-4 py-2.5 text-sm text-left text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2"
                                >
                                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 19V8l9-5 9 5v11a1 1 0 01-1 1H4a1 1 0 01-1-1z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6" />
                                    </svg>
                                    Marcar como não lida
                                </button>
                                <div class="border-t border-gray-100 my-1"></div>

                                <!-- Mover para -->
                                <div class="relative">
                                    <button
                                        @click.stop="showMoveSubmenu = !showMoveSubmenu"
                                        class="w-full px-4 py-2.5 text-sm text-left text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2"
                                    >
                                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                        </svg>
                                        <span class="flex-1">Mover para...</span>
                                        <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>

                                    <!-- Submenu de pastas -->
                                    <div
                                        v-if="showMoveSubmenu"
                                        class="absolute left-full top-0 ml-1 w-48 bg-white rounded-xl shadow-lg border z-30 py-1 max-h-64 overflow-y-auto"
                                    >
                                        <button
                                            v-for="folder in moveTargetFolders"
                                            :key="folder.path"
                                            @click="batchMoveMessages(folder.path)"
                                            class="w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-50 transition-colors truncate"
                                        >
                                            {{ getFolderDisplayName(folder.path) }}
                                        </button>
                                    </div>
                                </div>

                                <div class="border-t border-gray-100 my-1"></div>
                                <button
                                    @click="batchDeleteMessages"
                                    class="w-full px-4 py-2.5 text-sm text-left text-red-600 hover:bg-red-50 transition-colors flex items-center gap-2"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Excluir
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Search bar -->
                    <div class="flex-1 max-w-md mx-4">
                        <div class="relative">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar mensagens..."
                                class="w-full pl-9 pr-4 py-1.5 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent transition-shadow bg-gray-50 focus:bg-white"
                                :style="{ '--tw-ring-color': primaryColor + '40' }"
                                @keyup.enter="performSearch"
                                @keyup.escape="clearSearch"
                            />
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <button
                                v-if="searchQuery"
                                @click="clearSearch"
                                class="absolute right-2 top-1/2 -translate-y-1/2 p-0.5 text-gray-400 hover:text-gray-600"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <h2 class="text-base font-semibold text-gray-800">
                            <span v-if="searchResults">Resultados</span>
                            <span v-else>{{ getFolderDisplayName(currentFolder) }}</span>
                        </h2>
                        <span v-if="!searchResults && pagination.total > 0" class="text-sm text-gray-400">
                            ({{ pagination.total }} {{ pagination.total === 1 ? 'mensagem' : 'mensagens' }})
                        </span>
                        <span v-if="searchResults" class="text-sm text-gray-400">
                            ({{ searchResults.total }} {{ searchResults.total === 1 ? 'resultado' : 'resultados' }})
                        </span>
                    </div>
                </div>

                <!-- Message List Card -->
                <main class="flex-1 flex flex-col overflow-hidden bg-white rounded-2xl shadow-lg">

                <!-- Error Alert -->
                <div v-if="error" class="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm flex items-center shadow-sm">
                    <svg class="h-5 w-5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    {{ error }}
                </div>

                <!-- Loading search -->
                <div v-if="isSearching" class="flex items-center justify-center py-8">
                    <div class="text-sm text-gray-500">Buscando...</div>
                </div>

                <!-- Message List -->
                <div v-else class="flex-1 overflow-y-auto">
                    <MessageList
                        :messages="searchResults ? searchResults.messages : messages"
                        :current-folder="currentFolder"
                        :primary-color="primaryColor"
                        :selected-uids="selectedUids"
                        @toggle-select="toggleSelect"
                        @toggle-select-all="toggleSelectAll"
                    />
                </div>

                <!-- Pagination (only when not searching) -->
                <Pagination
                    v-if="!searchResults"
                    :pagination="pagination"
                    :base-url="getCurrentUrl()"
                    :primary-color="primaryColor"
                />
                </main>
            </div>
        </div>
    </div>
</template>
