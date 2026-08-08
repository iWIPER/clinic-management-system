/**
 * This will be loaded automatically by Vite.
 */
import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

// Não fixar o X-XSRF-TOKEN manualmente aqui: com `withCredentials = true`, o
// próprio axios já lê o cookie XSRF-TOKEN e manda o header certo a cada
// requisição (xsrfCookieName/xsrfHeaderName, ambos com o nome padrão que
// o Laravel usa). Fixar um valor estático uma vez só no carregamento da
// página corre o risco de ficar desatualizado se o cookie for renovado
// depois — pior, sem sinalizar erro nenhum: a requisição de escrita
// simplesmente falha (419) enquanto leituras (GET, sem CSRF) continuam OK,
// o que parece exatamente "editar/excluir não funciona, mas a lista carrega".

// Sessão expirada/inválida (ex.: SESSION_LIFETIME estourou com a aba aberta)
// ou token CSRF desatualizado (419) — sem isso, qualquer tela que usa axios
// direto (não uma navegação Inertia) falha em silêncio: os refs ficam vazios
// ou a escrita não é aplicada, e a única pista é um toast que passa
// despercebido. Navegações Inertia normais já lidam com isso sozinhas; isto
// cobre as que usam axios cru (ex.: painel de Tarefas). Redireciona pro
// login de verdade — que também renova o token CSRF — não é um retry
// disfarçado.
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401 || error.response?.status === 419) {
            window.location.href = '/login';
            return new Promise(() => {}); // navegando embora — não resolve nem rejeita
        }
        return Promise.reject(error);
    }
);
