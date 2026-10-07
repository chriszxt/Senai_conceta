async function fazerLogin(event) {

    event.preventDefault();

    const email = document.getElementById("loginEmail").value;
    const senha = document.getElementById("loginSenha").value;

    const resposta = await fazerRequisicao(
        `${API_URL}?rota=login`,
        {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                email: email,
                senha: senha
            })
        }
    );

    mostrarMensagem("mensagemLogin", resposta.mensagem, resposta.sucesso);

    if (resposta.sucesso) {

        setTimeout(() => {
            fecharModal("modalLogin");
            location.reload();
        }, 800);
    }
}


async function fazerCadastro(event) {

    event.preventDefault();

    const formulario = document.getElementById("formCadastro");

    const dados = new FormData(formulario);

    const resposta = await fazerRequisicao(
        `${API_URL}?rota=cadastro`,
        {
            method: "POST",
            body: dados
        }
    );

    mostrarMensagem(
        "mensagemCadastro",
        resposta.mensagem,
        resposta.sucesso
    );

    if (resposta.sucesso) {

        formulario.reset();

        setTimeout(() => {
            fecharModal("modalCadastro");
            abrirModal("modalLogin");
        }, 1000);
    }
}


async function sair() {

    const resposta = await fazerRequisicao(
        `${API_URL}?rota=logout`,
        {
            method: "POST"
        }
    );

    if (resposta.sucesso) {
        location.reload();
    }
}


function abrirModal(id) {
    document.getElementById(id).style.display = "flex";
}


function fecharModal(id) {
    document.getElementById(id).style.display = "none";
}


function abrirLogin() {
    abrirModal("modalLogin");
}


function abrirCadastro() {
    fecharModal("modalLogin");
    abrirModal("modalCadastro");
}


function mostrarMensagem(id, texto, sucesso) {

    const elemento = document.getElementById(id);

    elemento.textContent = texto;

    elemento.className = sucesso
        ? "mensagem sucesso"
        : "mensagem erro";
}


window.addEventListener("click", function(event) {

    if (event.target.classList.contains("modal")) {
        event.target.style.display = "none";
    }

});