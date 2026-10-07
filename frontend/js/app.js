let usuarioAtual = null;


document.addEventListener("DOMContentLoaded", async () => {

    await verificarSessao();

    await carregarPublicacoes();

});


async function verificarSessao() {

    const resposta = await fazerRequisicao(
        `${API_URL}?rota=sessao`
    );

    usuarioAtual = resposta.logado
        ? resposta.usuario
        : null;

    atualizarCabecalho();
}


function atualizarCabecalho() {

    const areaUsuario = document.getElementById("areaUsuario");

    if (!areaUsuario) {
        return;
    }

    if (usuarioAtual) {

        areaUsuario.innerHTML = `
            <button onclick="abrirPublicacao()">
                Publicar
            </button>

            <button onclick="abrirPerfil('${usuarioAtual.username}')">
                Meu perfil
            </button>

            <button onclick="sair()">
                Sair
            </button>
        `;

    } else {

        areaUsuario.innerHTML = `
            <button onclick="abrirLogin()">
                Entrar
            </button>

            <button onclick="abrirCadastro()">
                Cadastrar
            </button>
        `;
    }
}


async function carregarPublicacoes() {

    const feed = document.getElementById("feed");

    const dados = await fazerRequisicao(
        `${API_URL}?rota=publicacoes`
    );

    if (!Array.isArray(dados)) {
        feed.innerHTML = `
            <div class="mensagem erro">
                Não foi possível carregar as publicações.
            </div>
        `;
        return;
    }

    feed.innerHTML = "";

    if (dados.length === 0) {

        feed.innerHTML = `
            <div class="vazio">
                Nenhuma publicação encontrada.
            </div>
        `;

        return;
    }

    dados.forEach(publicacao => {

        const card = document.createElement("article");

        card.className = "publicacao";

        let imagem = "";

        if (publicacao.imagem) {

            imagem = `
                <img
                    src="../backend/uploads/${publicacao.imagem}"
                    class="imagem-publicacao"
                    alt="Imagem da publicação"
                >
            `;
        }

        const curtido = Number(publicacao.usuario_curtiu) === 1;

        const botaoCurtir = curtido
            ? `♥ ${publicacao.curtidas}`
            : `♡ ${publicacao.curtidas}`;

        let excluir = "";

        if (
            usuarioAtual &&
            Number(usuarioAtual.id_usuario) === Number(publicacao.id_usuario)
        ) {

            excluir = `
                <button
                    class="botao-excluir"
                    onclick="confirmarExclusao(${publicacao.id_publicacao})"
                >
                    Excluir
                </button>
            `;
        }

        card.innerHTML = `
            <div class="usuario-publicacao">

                <img
                    src="../backend/uploads/${publicacao.foto}"
                    class="foto-perfil"
                    alt="Foto do usuário"
                >

                <div class="dados-usuario">

                    <strong>
                        ${escaparHTML(publicacao.nome)}
                    </strong>

                    <span>
                        @${escaparHTML(publicacao.username)}
                    </span>

                </div>

            </div>

            <p class="texto-publicacao">
                ${escaparHTML(publicacao.texto)}
            </p>

            ${imagem}

            <div class="acoes-publicacao">

                <button
                    class="botao-curtir ${curtido ? "curtido" : ""}"
                    onclick="curtirPublicacao(${publicacao.id_publicacao})"
                >
                    ${botaoCurtir}
                </button>

                ${excluir}

                <span>
                    ${formatarData(publicacao.datahora_publicacao)}
                </span>

            </div>
        `;

        feed.appendChild(card);
    });
}


async function curtirPublicacao(id) {

    if (!usuarioAtual) {

        abrirLogin();

        return;
    }

    const resposta = await fazerRequisicao(
        `${API_URL}?rota=curtir`,
        {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                id_publicacao: id
            })
        }
    );

    if (!resposta.sucesso) {

        mostrarMensagem(
            "mensagemGeral",
            resposta.mensagem,
            false
        );

        return;
    }

    carregarPublicacoes();
}


function abrirPublicacao() {

    if (!usuarioAtual) {

        abrirLogin();

        return;
    }

    abrirModal("modalPublicacao");
}


async function publicar(event) {

    event.preventDefault();

    const formulario = document.getElementById("formPublicacao");

    const dados = new FormData(formulario);

    const resposta = await fazerRequisicao(
        `${API_URL}?rota=publicar`,
        {
            method: "POST",
            body: dados
        }
    );

    mostrarMensagem(
        "mensagemPublicacao",
        resposta.mensagem,
        resposta.sucesso
    );

    if (resposta.sucesso) {

        formulario.reset();

        setTimeout(() => {

            fecharModal("modalPublicacao");

            carregarPublicacoes();

        }, 800);
    }
}


function confirmarExclusao(id) {

    document.getElementById("idExcluir").value = id;

    abrirModal("modalExcluir");
}


async function excluirPublicacao() {

    const id = document.getElementById("idExcluir").value;

    const resposta = await fazerRequisicao(
        `${API_URL}?rota=excluir`,
        {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                id_publicacao: id
            })
        }
    );

    fecharModal("modalExcluir");

    mostrarMensagem(
        "mensagemGeral",
        resposta.mensagem,
        resposta.sucesso
    );

    if (resposta.sucesso) {
        carregarPublicacoes();
    }
}


async function pesquisarUsuario() {

    const campo = document.getElementById("pesquisaUsuario");

    const username = campo.value.trim();

    if (username === "") {
        return;
    }

    const resposta = await fazerRequisicao(
        `${API_URL}?rota=pesquisar&username=${encodeURIComponent(username)}`
    );

    const resultados = document.getElementById("resultadosPesquisa");

    resultados.innerHTML = "";

    if (!resposta.usuarios || resposta.usuarios.length === 0) {

        resultados.innerHTML = `
            <p>Nenhum usuário encontrado.</p>
        `;

        abrirModal("modalPesquisa");

        return;
    }

    resposta.usuarios.forEach(usuario => {

        const item = document.createElement("div");

        item.className = "resultado-usuario";

        item.innerHTML = `
            <img
                src="../backend/uploads/${usuario.foto}"
                class="foto-perfil"
            >

            <div>
                <strong>${escaparHTML(usuario.nome)}</strong>
                <span>@${escaparHTML(usuario.username)}</span>
            </div>

            <button
                onclick="abrirPerfil('${escaparHTML(usuario.username)}')"
            >
                Ver perfil
            </button>
        `;

        resultados.appendChild(item);
    });

    abrirModal("modalPesquisa");
}


async function abrirPerfil(username) {

    const resposta = await fazerRequisicao(
        `${API_URL}?rota=perfil&username=${encodeURIComponent(username)}`
    );

    if (!resposta.sucesso) {

        mostrarMensagem(
            "mensagemGeral",
            resposta.mensagem,
            false
        );

        return;
    }

    const usuario = resposta.usuario;

    document.getElementById("perfilConteudo").innerHTML = `

        <div class="perfil-cabecalho">

            <img
                src="../backend/uploads/${usuario.foto}"
                class="foto-perfil-grande"
            >

            <h2>${escaparHTML(usuario.nome)}</h2>

            <p>
                @${escaparHTML(usuario.username)}
            </p>

        </div>

        <div class="perfil-estatisticas">

            <div>
                <strong>${usuario.quantidade_publicacoes}</strong>
                <span>Publicações</span>
            </div>

            <div>
                <strong>${usuario.quantidade_curtidas}</strong>
                <span>Curtidas recebidas</span>
            </div>

        </div>

        <h3>Publicações</h3>

        <div class="perfil-publicacoes">

            ${usuario.publicacoes.length === 0
                ? "<p>Este usuário ainda não possui publicações.</p>"
                : usuario.publicacoes.map(publicacao => `

                    <div class="perfil-post">

                        <p>
                            ${escaparHTML(publicacao.texto)}
                        </p>

                        ${
                            publicacao.imagem
                            ? `
                                <img
                                    src="../backend/uploads/${publicacao.imagem}"
                                >
                            `
                            : ""
                        }

                        <small>
                            ${formatarData(publicacao.datahora_publicacao)}
                        </small>

                    </div>

                `).join("")
            }

        </div>
    `;

    fecharModal("modalPesquisa");

    abrirModal("modalPerfil");
}


function formatarData(data) {

    const dataObj = new Date(
        data.replace(" ", "T")
    );

    return dataObj.toLocaleString("pt-BR");
}


function escaparHTML(texto) {

    const div = document.createElement("div");

    div.textContent = texto;

    return div.innerHTML;
}