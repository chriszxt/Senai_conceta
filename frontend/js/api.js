const API_URL = "../backend/index.php";

async function fazerRequisicao(url, opcoes = {}) {

    try {

        const resposta = await fetch(url, opcoes);

        const dados = await resposta.json();

        return dados;

    } catch (erro) {

        return {
            sucesso: false,
            mensagem: "Erro ao conectar com o servidor."
        };
    }
}