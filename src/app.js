import express from "express";
import dotenv from "dotenv";
import crypto from "crypto";
import bcrypt from "bcryptjs";
import mysql from "mysql2/promise";

dotenv.config();

const bdPool = mysql.createPool({
  host: process.env.BD_HOST,
  user: process.env.BD_USUARIO,
  password: process.env.BD_SENHA,
  database: process.env.BD_NOME,
  waitForConnections: true,
  connectionLimit: 10,
});

const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static("public"));

async function buscarUsuarioPorEmail(email) {
  const [rows] = await bdPool.query("SELECT * FROM `usuarios` WHERE `email`=? LIMIT 1", [email]);
  return rows[0] || null;
}

async function buscarUsuarioPorLogin(login) {
  const [rows] = await bdPool.query("SELECT * FROM `usuarios` WHERE `login`=? LIMIT 1", [login]);
  return rows[0] || null;
}

async function criarSessao(usuarioId, ip, agente) {
  const token = crypto.randomBytes(32).toString("hex");
  const expira = new Date(Date.now() + 1000 * 60 * 60 * 24 * 7);
  await bdPool.query(
    "INSERT INTO `sessoes` (`usuario_id`,`token`,`expira_em`,`ip`,`agente_usuario`) VALUES (?,?,?,?,?)",
    [usuarioId, token, expira, ip || null, agente || null]
  );
  return token;
}

async function validarSessao(token) {
  const [rows] = await bdPool.query(
    "SELECT s.*, u.* FROM `sessoes` s JOIN `usuarios` u ON u.`id`=s.`usuario_id` WHERE s.`token`=? AND s.`revogada_em` IS NULL AND s.`expira_em`>NOW() LIMIT 1",
    [token]
  );
  if (!rows[0]) return null;
  return { usuario: {
    id: rows[0].id,
    nome: rows[0].nome,
    email: rows[0].email,
    ativo: rows[0].ativo,
  } };
}

function authMiddleware(req, res, next) {
  const h = req.headers.authorization || "";
  const parts = h.split(" ");
  const token = parts.length === 2 && parts[0] === "Bearer" ? parts[1] : null;
  if (!token) return res.status(401).json({ erro: "nao_autenticado" });
  validarSessao(token).then((ctx) => {
    if (!ctx) return res.status(401).json({ erro: "sessao_invalida" });
    if (!ctx.usuario.ativo) return res.status(403).json({ erro: "acesso_bloqueado" });
    req.usuario = ctx.usuario;
    next();
  }).catch(() => res.status(500).json({ erro: "falha_autenticacao" }));
}

function validarEmail(v){return /.+@.+\..+/.test(v)}
function validarSenha(v){return typeof v==="string" && v.length>=8 && /[A-Za-z]/.test(v) && /\d/.test(v)}
function limparCPF(v){return (v||"").replace(/\D/g,"")}
function validarCPF(c){c=limparCPF(c);if(!c||c.length!==11||/^([0-9])\1*$/.test(c))return false;let s=0;for(let i=0;i<9;i++)s+=parseInt(c[i])*(10-i);let d1=(s*10)%11;if(d1===10)d1=0;if(d1!==parseInt(c[9]))return false;s=0;for(let i=0;i<10;i++)s+=parseInt(c[i])*(11-i);let d2=(s*10)%11;if(d2===10)d2=0;return d2===parseInt(c[10])}
function validarTelefone(t){return /^[0-9()+\-\s]{8,20}$/.test(t||"")}

app.post("/cadastro", async (req, res) => {
  const { nome_completo, cpf, email, telefone, login, senha, aceitar_termos } = req.body;
  if (!nome_completo || !cpf || !email || !telefone || !login || !senha || !aceitar_termos) return res.status(400).json({ erro: "dados_obrigatorios" });
  if (!validarEmail(email)) return res.status(422).json({ erro: "email_invalido" });
  if (!validarSenha(senha)) return res.status(422).json({ erro: "senha_fraca" });
  if (!validarCPF(cpf)) return res.status(422).json({ erro: "cpf_invalido" });
  if (!validarTelefone(telefone)) return res.status(422).json({ erro: "telefone_invalido" });
  const [dup] = await bdPool.query("SELECT 1 FROM `usuarios` WHERE `email`=? OR `login`=? OR `cpf`=? LIMIT 1", [email, login, limparCPF(cpf)]);
  if (dup.length>0) return res.status(409).json({ erro: "duplicado" });
  const hash = await bcrypt.hash(senha, 12);
  const [r] = await bdPool.query(
    "INSERT INTO `usuarios` (`nome`,`cpf`,`telefone`,`email`,`login`,`senha_hash`,`termos_aceitos`,`termos_aceitos_em`,`ativo`) VALUES (?,?,?,?,?,?,1,NOW(),0)",
    [nome_completo, limparCPF(cpf), telefone, email, login, hash]
  );
  await bdPool.query("INSERT INTO `logs_acesso` (`usuario_id`,`acao`,`sucesso`,`ip`,`agente_usuario`) VALUES (?,?,?,?,?)", [r.insertId, "cadastro", 1, req.ip, req.headers["user-agent"]||""]);
  res.json({ usuario_id: r.insertId });
});

app.post("/login", async (req, res) => {
  const { email, login, senha } = req.body;
  const u = email ? await buscarUsuarioPorEmail(email) : await buscarUsuarioPorLogin(login);
  if (!u) { await bdPool.query("INSERT INTO `logs_acesso` (`usuario_id`,`acao`,`sucesso`,`ip`,`agente_usuario`) VALUES (NULL,?,0,?,?)", ["login", req.ip, req.headers["user-agent"]||""]); return res.status(401).json({ erro: "credenciais_invalidas" }); }
  if (u.bloqueado_ate && new Date(u.bloqueado_ate) > new Date()) { await bdPool.query("INSERT INTO `logs_acesso` (`usuario_id`,`acao`,`sucesso`,`ip`,`agente_usuario`) VALUES (?,?,?,?,?)", [u.id, "login_bloqueado", 0, req.ip, req.headers["user-agent"]||""]); return res.status(429).json({ erro: "bloqueado_temporariamente" }); }
  const ok = await bcrypt.compare(senha, Buffer.isBuffer(u.senha_hash) ? u.senha_hash.toString() : u.senha_hash);
  if (!ok) {
    const tent = (u.tentativas_falhas||0)+1;
    let bloqueadoAte = null;
    if (tent>=5) bloqueadoAte = new Date(Date.now()+15*60*1000);
    await bdPool.query("UPDATE `usuarios` SET `tentativas_falhas`=?, `bloqueado_ate`=? WHERE `id`=?", [tent, bloqueadoAte, u.id]);
    await bdPool.query("INSERT INTO `logs_acesso` (`usuario_id`,`acao`,`sucesso`,`ip`,`agente_usuario`) VALUES (?,?,?,?,?)", [u.id, "login", 0, req.ip, req.headers["user-agent"]||""]); 
    return res.status(401).json({ erro: "credenciais_invalidas" });
  }
  await bdPool.query("UPDATE `usuarios` SET `tentativas_falhas`=0, `bloqueado_ate`=NULL, `ultimo_login_em`=NOW() WHERE `id`=?", [u.id]);
  const token = await criarSessao(u.id, req.ip, req.headers["user-agent"] || "");
  await bdPool.query("INSERT INTO `logs_acesso` (`usuario_id`,`acao`,`sucesso`,`ip`,`agente_usuario`) VALUES (?,?,?,?,?)", [u.id, "login", 1, req.ip, req.headers["user-agent"]||""]); 
  res.json({ token });
});

app.post("/logout", authMiddleware, async (req, res) => {
  const h = req.headers.authorization || "";
  const token = h.split(" ")[1];
  await bdPool.query("UPDATE `sessoes` SET `revogada_em`=NOW() WHERE `token`=?", [token]);
  res.json({ ok: true });
});

app.get("/painel", authMiddleware, async (req, res) => {
  const [ass] = await bdPool.query(
    "SELECT a.*, p.`nome` AS plano_nome, p.`codigo` AS plano_codigo FROM `assinaturas` a JOIN `planos` p ON p.`id`=a.`plano_id` WHERE a.`usuario_id`=? ORDER BY a.`atualizado_em` DESC LIMIT 1",
    [req.usuario.id]
  );
  const assinatura = ass[0] || null;
  const [pags] = await bdPool.query(
    "SELECT * FROM `pagamentos` WHERE `assinatura_id`=? ORDER BY `criado_em` DESC LIMIT 20",
    [assinatura ? assinatura.id : 0]
  );
  res.json({ usuario: req.usuario, assinatura, pagamentos: pags });
});

app.get("/pagamento/opcoes", (req, res) => {
  res.json({ metodos: ["pix","cartao_credito","cartao_debito"] });
});

app.get("/planos", async (req, res) => {
  const [rows] = await bdPool.query("SELECT `id`,`nome`,`codigo`,`periodo_cobranca`,`preco_centavos`,`moeda`,`ativo` FROM `planos` WHERE `ativo`=1 ORDER BY FIELD(`codigo`,'mensal','semestral','anual')");
  res.json({ planos: rows });
});

app.post("/contratar", async (req, res) => {
  const { plano_codigo, email, nome } = req.body;
  if (!plano_codigo || !email || !nome) { await bdPool.query("INSERT INTO `logs_acesso` (`usuario_id`,`acao`,`sucesso`,`ip`,`agente_usuario`) VALUES (NULL,?,0,?,?)", ["pix_contratar", req.ip, req.headers["user-agent"]||""]); return res.status(400).json({ erro: "dados_obrigatorios" }); }
  if (!validarEmail(email)) { await bdPool.query("INSERT INTO `logs_acesso` (`usuario_id`,`acao`,`sucesso`,`ip`,`agente_usuario`) VALUES (NULL,?,0,?,?)", ["pix_contratar", req.ip, req.headers["user-agent"]||""]); return res.status(422).json({ erro: "email_invalido" }); }
  const [plRows] = await bdPool.query("SELECT * FROM `planos` WHERE `codigo`=? AND `ativo`=1", [plano_codigo]);
  const plano = plRows[0];
  if (!plano) { await bdPool.query("INSERT INTO `logs_acesso` (`usuario_id`,`acao`,`sucesso`,`ip`,`agente_usuario`) VALUES (NULL,?,0,?,?)", ["pix_contratar", req.ip, req.headers["user-agent"]||""]); return res.status(404).json({ erro: "plano_inexistente" }); }
  const inicio = new Date();
  let fim = null;
  if (plano.periodo_cobranca === "mensal") fim = new Date(Date.now() + 1000*60*60*24*30);
  if (plano.periodo_cobranca === "semestral") fim = new Date(Date.now() + 1000*60*60*24*182);
  if (plano.periodo_cobranca === "anual") fim = new Date(Date.now() + 1000*60*60*24*365);
  const [r] = await bdPool.query(
    "INSERT INTO `assinaturas` (`usuario_id`,`plano_id`,`status`,`inicio_em`,`fim_em`,`contratante_email`,`contratante_nome`) VALUES (NULL,?,?,?,?,?,?)",
    [plano.id, "pendente", inicio, fim, email, nome]
  );
  const externo = crypto.randomBytes(8).toString("hex");
  await bdPool.query(
    "INSERT INTO `pagamentos` (`assinatura_id`,`valor_centavos`,`metodo`,`status`,`id_externo`) VALUES (?,?,?,?,?)",
    [r.insertId, plano.preco_centavos, "pix", "pendente", externo]
  );
  await bdPool.query("INSERT INTO `logs_acesso` (`usuario_id`,`acao`,`sucesso`,`ip`,`agente_usuario`) VALUES (NULL,?,1,?,?)", ["pix_contratar", req.ip, req.headers["user-agent"]||""]); 
  const pix = `PIX:FLOWHEDGE:${externo}`;
  res.json({ assinatura_id: r.insertId, id_externo: externo, pix });
});

app.post("/assinar", authMiddleware, async (req, res) => {
  const { codigo_plano } = req.body;
  const [plRows] = await bdPool.query("SELECT * FROM `planos` WHERE `codigo`=? AND `ativo`=1", [codigo_plano]);
  const plano = plRows[0];
  if (!plano) return res.status(404).json({ erro: "plano_inexistente" });
  const inicio = new Date();
  let fim = null;
  if (plano.periodo_cobranca === "mensal") fim = new Date(Date.now() + 1000*60*60*24*30);
  if (plano.periodo_cobranca === "semestral") fim = new Date(Date.now() + 1000*60*60*24*182);
  if (plano.periodo_cobranca === "anual") fim = new Date(Date.now() + 1000*60*60*24*365);
  const [r] = await bdPool.query(
    "INSERT INTO `assinaturas` (`usuario_id`,`plano_id`,`status`,`inicio_em`,`fim_em`) VALUES (?,?,?,?,?)",
    [req.usuario.id, plano.id, "pendente", inicio, fim]
  );
  res.json({ assinatura_id: r.insertId });
});

app.post("/pagamento/confirmar", authMiddleware, async (req, res) => {
  const { assinatura_id, metodo, valor_centavos, id_externo } = req.body;
  const [assRows] = await bdPool.query("SELECT * FROM `assinaturas` WHERE `id`=? AND `usuario_id`=?", [assinatura_id, req.usuario.id]);
  const ass = assRows[0];
  if (!ass) return res.status(404).json({ erro: "assinatura_inexistente" });
  const [pRows] = await bdPool.query("SELECT * FROM `pagamentos` WHERE `assinatura_id`=? AND `id_externo`=? ORDER BY `criado_em` DESC LIMIT 1", [assinatura_id, id_externo||null]);
  if (pRows[0]) {
    await bdPool.query("UPDATE `pagamentos` SET `status`='pago', `pago_em`=NOW() WHERE `id`=?", [pRows[0].id]);
  } else {
    await bdPool.query(
      "INSERT INTO `pagamentos` (`assinatura_id`,`valor_centavos`,`metodo`,`status`,`id_externo`,`pago_em`) VALUES (?,?,?,?,?,NOW())",
      [assinatura_id, valor_centavos, metodo, "pago", id_externo || null]
    );
  }
  await bdPool.query("UPDATE `assinaturas` SET `status`='ativa' WHERE `id`=?", [assinatura_id]);
  await bdPool.query("UPDATE `usuarios` SET `ativo`=1 WHERE `id`=?", [req.usuario.id]);
  res.json({ ok: true });
});

app.post("/pagamento/iniciar", authMiddleware, async (req, res) => {
  const { assinatura_id, metodo, valor_centavos } = req.body;
  const [assRows] = await bdPool.query("SELECT * FROM `assinaturas` WHERE `id`=? AND `usuario_id`=?", [assinatura_id, req.usuario.id]);
  const ass = assRows[0];
  if (!ass || ass.status!=="pendente") return res.status(400).json({ erro: "assinatura_invalida" });
  const externo = crypto.randomBytes(8).toString("hex");
  await bdPool.query("INSERT INTO `pagamentos` (`assinatura_id`,`valor_centavos`,`metodo`,`status`,`id_externo`) VALUES (?,?,?,?,?)", [assinatura_id, valor_centavos, metodo, "pendente", externo]);
  res.json({ id_externo: externo });
});

app.get("/pagamento/status", async (req, res) => {
  const assinaturaId = req.query.assinatura_id ? Number(req.query.assinatura_id) : null;
  const idExterno = req.query.id_externo || null;
  let rows;
  if (idExterno) {
    [rows] = await bdPool.query("SELECT * FROM `pagamentos` WHERE `id_externo`=? ORDER BY `criado_em` DESC LIMIT 1", [idExterno]);
  } else if (assinaturaId) {
    [rows] = await bdPool.query("SELECT * FROM `pagamentos` WHERE `assinatura_id`=? ORDER BY `criado_em` DESC LIMIT 1", [assinaturaId]);
  } else {
    return res.status(400).json({ erro: "parametros_invalidos" });
  }
  res.json({ pagamento: rows[0]||null });
});

app.post("/webhook/pagamento", async (req, res) => {
  const { id_externo, status } = req.body;
  const [rows] = await bdPool.query("SELECT * FROM `pagamentos` WHERE `id_externo`=? LIMIT 1", [id_externo]);
  const p = rows[0];
  if (!p) return res.status(404).json({ erro: "pagamento_inexistente" });
  if (status==="pago") {
    await bdPool.query("UPDATE `pagamentos` SET `status`='pago', `pago_em`=NOW() WHERE `id`=?", [p.id]);
    await bdPool.query("UPDATE `assinaturas` SET `status`='ativa' WHERE `id`=?", [p.assinatura_id]);
    const [ass] = await bdPool.query("SELECT `usuario_id`,`contratante_email` FROM `assinaturas` WHERE `id`=?", [p.assinatura_id]);
    const uId = ass[0]?.usuario_id;
    const email = ass[0]?.contratante_email;
    if (uId) await bdPool.query("UPDATE `usuarios` SET `ativo`=1 WHERE `id`=?", [uId]);
    if (!uId && email) {
      const u = await buscarUsuarioPorEmail(email);
      if (u) {
        await bdPool.query("UPDATE `assinaturas` SET `usuario_id`=? WHERE `id`=?", [u.id, p.assinatura_id]);
        await bdPool.query("UPDATE `usuarios` SET `ativo`=1 WHERE `id`=?", [u.id]);
      }
    }
  } else if (status==="falhou") {
    await bdPool.query("UPDATE `pagamentos` SET `status`='falhou' WHERE `id`=?", [p.id]);
  }
  res.json({ ok: true });
});

app.post("/pix/confirmar", async (req, res) => {
  const { id_externo } = req.body;
  if (!id_externo) return res.status(400).json({ erro: "id_externo_obrigatorio" });
  const [rows] = await bdPool.query("SELECT * FROM `pagamentos` WHERE `id_externo`=? LIMIT 1", [id_externo]);
  const p = rows[0];
  if (!p) return res.status(404).json({ erro: "pagamento_inexistente" });
  await bdPool.query("UPDATE `pagamentos` SET `status`='pago', `pago_em`=NOW() WHERE `id`=?", [p.id]);
  await bdPool.query("UPDATE `assinaturas` SET `status`='ativa' WHERE `id`=?", [p.assinatura_id]);
  const [ass] = await bdPool.query("SELECT `usuario_id`,`contratante_email` FROM `assinaturas` WHERE `id`=?", [p.assinatura_id]);
  const uId = ass[0]?.usuario_id;
  const email = ass[0]?.contratante_email;
  if (uId) await bdPool.query("UPDATE `usuarios` SET `ativo`=1 WHERE `id`=?", [uId]);
  if (!uId && email) {
    const u = await buscarUsuarioPorEmail(email);
    if (u) {
      await bdPool.query("UPDATE `assinaturas` SET `usuario_id`=? WHERE `id`=?", [u.id, p.assinatura_id]);
      await bdPool.query("UPDATE `usuarios` SET `ativo`=1 WHERE `id`=?", [u.id]);
    }
  }
  res.json({ ok: true });
});

app.post("/cadastro/vincular", async (req, res) => {
  const { email } = req.body;
  if (!email) return res.status(400).json({ erro: "email_obrigatorio" });
  const u = await buscarUsuarioPorEmail(email);
  if (!u) return res.status(404).json({ erro: "usuario_inexistente" });
  await bdPool.query("UPDATE `assinaturas` SET `usuario_id`=? WHERE `usuario_id` IS NULL AND `contratante_email`=?", [u.id, email]);
  const [ass] = await bdPool.query("SELECT COUNT(*) AS c FROM `assinaturas` WHERE `usuario_id`=? AND `status`='ativa'", [u.id]);
  if (ass[0].c>0) await bdPool.query("UPDATE `usuarios` SET `ativo`=1 WHERE `id`=?", [u.id]);
  res.json({ ok: true });
});

app.post("/senha/recuperar", async (req, res) => {
  const { email } = req.body;
  const u = await buscarUsuarioPorEmail(email);
  if (!u) return res.status(404).json({ erro: "usuario_inexistente" });
  const token = crypto.randomBytes(32).toString("hex");
  const expira = new Date(Date.now() + 1000 * 60 * 60);
  await bdPool.query(
    "INSERT INTO `recuperacoes_senha` (`usuario_id`,`token`,`expira_em`) VALUES (?,?,?)",
    [u.id, token, expira]
  );
  await bdPool.query(
    "INSERT INTO `notificacoes` (`usuario_id`,`tipo`,`status`,`metadados`) VALUES (?,?,?,JSON_OBJECT('token',?))",
    [u.id, "confirmacao_pagamento", "em_fila", token]
  );
  res.json({ token });
});

app.post("/senha/redefinir", async (req, res) => {
  const { token, nova_senha } = req.body;
  const [rows] = await bdPool.query("SELECT * FROM `recuperacoes_senha` WHERE `token`=? AND `usado_em` IS NULL AND `expira_em`>NOW()", [token]);
  const r = rows[0];
  if (!r) return res.status(400).json({ erro: "token_invalido" });
  const hash = await bcrypt.hash(nova_senha, 10);
  await bdPool.query("UPDATE `usuarios` SET `senha_hash`=? WHERE `id`=?", [hash, r.usuario_id]);
  await bdPool.query("UPDATE `recuperacoes_senha` SET `usado_em`=NOW() WHERE `id`=?", [r.id]);
  res.json({ ok: true });
});

app.get("/admin/usuarios", authMiddleware, async (req, res) => {
  const [rows] = await bdPool.query("SELECT `id`,`nome`,`email`,`ativo`,`criado_em`,`atualizado_em` FROM `usuarios` ORDER BY `criado_em` DESC LIMIT 100");
  res.json({ usuarios: rows });
});

app.post("/admin/usuarios/:id/ativar", authMiddleware, async (req, res) => {
  const id = Number(req.params.id);
  await bdPool.query("UPDATE `usuarios` SET `ativo`=1 WHERE `id`=?", [id]);
  res.json({ ok: true });
});

app.post("/admin/usuarios/:id/bloquear", authMiddleware, async (req, res) => {
  const id = Number(req.params.id);
  await bdPool.query("UPDATE `usuarios` SET `ativo`=0 WHERE `id`=?", [id]);
  res.json({ ok: true });
});

app.get("/health", async (req, res) => {
  try {
    await bdPool.query("SELECT 1");
    res.json({ server: "ok", db: "ok" });
  } catch (e) {
    res.status(500).json({ server: "ok", db: "erro" });
  }
});

app.use(async (err, req, res, next) => {
  try {
    await bdPool.query(
      "INSERT INTO `erros_sistema` (`rota`,`mensagem`,`stack`,`status_code`,`ip`,`user_agent`) VALUES (?,?,?,?,?,?)",
      [req.path||"", err?.message||"", err?.stack||"", 500, req.ip||"", req.headers["user-agent"]||""]
    );
  } catch(e) {}
  res.status(500).json({ erro: "falha_interna" });
});

const porta = process.env.PORTA || 3000;
app.listen(porta, () => { console.log(`http://localhost:${porta}/`); });