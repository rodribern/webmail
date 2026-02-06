<script setup>
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    folders: {
        type: Array,
        default: () => []
    },
    currentFolder: {
        type: String,
        default: 'INBOX'
    },
    primaryColor: {
        type: String,
        default: '#3B82F6'
    }
});

// Configuração de pastas com ícones e cores
const folderConfig = {
    'INBOX': {
        icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        color: '#3B82F6',
        name: 'Caixa de Entrada'
    },
    'Sent': {
        icon: 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8',
        color: '#10B981',
        name: 'Enviados'
    },
    'Drafts': {
        icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        color: '#F59E0B',
        name: 'Rascunhos'
    },
    'Trash': {
        icon: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
        color: '#EF4444',
        name: 'Lixeira'
    },
    'Spam': {
        icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        color: '#F97316',
        name: 'Spam'
    },
    'Junk': {
        icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        color: '#F97316',
        name: 'Lixo Eletrônico'
    },
};

const systemFolderNames = ['inbox', 'sent', 'drafts', 'trash', 'spam', 'junk'];

const getConfig = (folder) => {
    if (folderConfig[folder.name]) {
        return folderConfig[folder.name];
    }
    for (const [key, config] of Object.entries(folderConfig)) {
        if (folder.name.includes(key)) {
            return config;
        }
    }
    return {
        icon: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        color: '#6B7280',
        name: folder.name
    };
};

const getFolderUrl = (folder) => {
    if (folder.path === 'INBOX') {
        return '/mail/inbox';
    }
    return `/mail/folder/${encodeURIComponent(folder.path)}`;
};

const isActive = (folder) => {
    return folder.path === props.currentFolder;
};

const isSystemFolder = (folder) => {
    const name = folder.name.toLowerCase();
    return systemFolderNames.some(s => name === s || name.includes(s));
};

// Gerenciamento de pastas
const showCreateInput = ref(false);
const newFolderName = ref('');
const isCreating = ref(false);
const createError = ref('');
const contextMenuFolder = ref(null);
const contextMenuPos = ref({ x: 0, y: 0 });
const showRenameInput = ref(null);
const renameName = ref('');

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const createFolder = async () => {
    const name = newFolderName.value.trim();
    if (!name) return;

    isCreating.value = true;
    createError.value = '';

    try {
        const response = await fetch('/api/mail/folders', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ name }),
        });

        if (response.ok) {
            newFolderName.value = '';
            showCreateInput.value = false;
            window.location.reload();
        } else {
            const data = await response.json();
            createError.value = data.error || 'Falha ao criar pasta.';
        }
    } catch (e) {
        createError.value = 'Erro de conexão.';
    } finally {
        isCreating.value = false;
    }
};

const showContextMenu = (event, folder) => {
    if (isSystemFolder(folder)) return;
    event.preventDefault();
    contextMenuFolder.value = folder;
    contextMenuPos.value = { x: event.clientX, y: event.clientY };
};

const hideContextMenu = () => {
    contextMenuFolder.value = null;
};

const startRename = (folder) => {
    showRenameInput.value = folder.path;
    renameName.value = folder.name;
    contextMenuFolder.value = null;
};

const renameFolder = async (folder) => {
    const name = renameName.value.trim();
    if (!name || name === folder.name) {
        showRenameInput.value = null;
        return;
    }

    try {
        const response = await fetch(`/api/mail/folders/${encodeURIComponent(folder.path)}`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ new_name: name }),
        });

        if (response.ok) {
            showRenameInput.value = null;
            window.location.reload();
        } else {
            const data = await response.json();
            alert(data.error || 'Falha ao renomear pasta.');
        }
    } catch (e) {
        alert('Erro de conexão.');
    }
};

const deleteFolder = async (folder) => {
    if (!confirm(`Excluir a pasta "${folder.name}"? Todo o conteúdo será perdido.`)) {
        return;
    }
    contextMenuFolder.value = null;

    try {
        const response = await fetch(`/api/mail/folders/${encodeURIComponent(folder.path)}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
        });

        if (response.ok) {
            window.location.reload();
        } else {
            const data = await response.json();
            alert(data.error || 'Falha ao excluir pasta.');
        }
    } catch (e) {
        alert('Erro de conexão.');
    }
};
</script>

<template>
    <nav class="space-y-1 px-4" @click="hideContextMenu">
        <Link
            v-for="folder in folders"
            :key="folder.path"
            :href="getFolderUrl(folder)"
            class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 ease-out"
            :class="{
                'shadow-lg scale-[1.02]': isActive(folder),
                'hover:bg-gray-50 hover:shadow hover:scale-[1.01]': !isActive(folder)
            }"
            :style="isActive(folder) ? {
                backgroundColor: getConfig(folder).color + '10',
                boxShadow: `0 4px 15px -3px ${getConfig(folder).color}40`
            } : {}"
            @contextmenu="showContextMenu($event, folder)"
        >
            <!-- Ícone colorido -->
            <div
                class="mr-3 p-1.5 rounded-lg transition-all duration-200"
                :style="{
                    backgroundColor: isActive(folder) ? getConfig(folder).color + '20' : 'transparent'
                }"
            >
                <svg
                    class="h-5 w-5 transition-transform duration-200 group-hover:scale-110"
                    :style="{ color: getConfig(folder).color }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" :d="getConfig(folder).icon" />
                </svg>
            </div>

            <!-- Nome da pasta ou input de rename -->
            <template v-if="showRenameInput === folder.path">
                <input
                    v-model="renameName"
                    class="flex-1 text-sm border border-gray-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-1"
                    @keyup.enter="renameFolder(folder)"
                    @keyup.escape="showRenameInput = null"
                    @click.prevent
                    autofocus
                />
            </template>
            <template v-else>
                <span
                    class="flex-1 transition-colors duration-200"
                    :class="{
                        'text-gray-900 font-semibold': isActive(folder),
                        'text-gray-600 group-hover:text-gray-900': !isActive(folder)
                    }"
                >
                    {{ getConfig(folder).name }}
                </span>
            </template>

            <!-- Badge de não lidos -->
            <span
                v-if="folder.unseen > 0 && showRenameInput !== folder.path"
                class="ml-2 px-2 py-0.5 text-xs font-bold rounded-full transition-all duration-200"
                :style="{
                    backgroundColor: isActive(folder) ? getConfig(folder).color : getConfig(folder).color + '20',
                    color: isActive(folder) ? 'white' : getConfig(folder).color
                }"
            >
                {{ folder.unseen }}
            </span>
        </Link>

        <!-- Botão criar pasta -->
        <div class="pt-2 border-t border-gray-100 mt-2">
            <div v-if="showCreateInput" class="px-3 py-2 space-y-2">
                <input
                    v-model="newFolderName"
                    type="text"
                    placeholder="Nome da pasta"
                    class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1"
                    maxlength="50"
                    @keyup.enter="createFolder"
                    @keyup.escape="showCreateInput = false"
                    autofocus
                />
                <div v-if="createError" class="text-xs text-red-500">{{ createError }}</div>
                <div class="flex items-center space-x-2">
                    <button
                        @click="createFolder"
                        :disabled="isCreating || !newFolderName.trim()"
                        class="px-3 py-1 text-xs text-white rounded-lg disabled:opacity-50"
                        :style="{ backgroundColor: primaryColor }"
                    >{{ isCreating ? '...' : 'Criar' }}</button>
                    <button
                        @click="showCreateInput = false"
                        class="px-3 py-1 text-xs text-gray-600 hover:bg-gray-100 rounded-lg"
                    >Cancelar</button>
                </div>
            </div>
            <button
                v-else
                @click="showCreateInput = true"
                class="w-full flex items-center px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-xl transition-colors"
            >
                <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Nova pasta
            </button>
        </div>

        <!-- Menu contextual -->
        <Teleport to="body">
            <div
                v-if="contextMenuFolder"
                class="fixed z-50 bg-white rounded-xl shadow-lg border py-1 min-w-[160px]"
                :style="{ left: contextMenuPos.x + 'px', top: contextMenuPos.y + 'px' }"
            >
                <button
                    @click="startRename(contextMenuFolder)"
                    class="w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-50 flex items-center space-x-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span>Renomear</span>
                </button>
                <button
                    @click="deleteFolder(contextMenuFolder)"
                    class="w-full px-4 py-2 text-sm text-left text-red-600 hover:bg-red-50 flex items-center space-x-2"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <span>Excluir</span>
                </button>
            </div>
        </Teleport>
    </nav>
</template>
