#!/usr/bin/env python3
"""
Extrai dados de PDFs de cotação gerados pelo sistema Cotaço.
Retorna JSON com dados estruturados para a apresentação de orçamento.
"""
import sys
import json
import re

try:
    import pdfplumber
except ImportError:
    print(json.dumps({"ok": False, "msg": "pdfplumber não instalado"}))
    sys.exit(0)


def extrair_cotacao(pdf_path):
    result = {
        "cliente": "",
        "vendedor": "",
        "email": "",
        "telefone": "",
        "vidas": "",
        "tipo": "Saúde",
        "planos": []
    }

    with pdfplumber.open(pdf_path) as pdf:
        if not pdf.pages:
            return result

        page = pdf.pages[0]
        text = page.extract_text() or ""
        lines = text.split("\n")

        # ── Extrair cabeçalho ──────────────────────────────────
        for line in lines[:10]:
            # Título: "STUDIODWG - COTAÇÃO SAÚDE (3 vidas)"
            m = re.match(r'^(.+?)\s*[-–]\s*COTAÇÃO\s+(.*?)\s*\((\d+)\s*vidas?\)', line, re.IGNORECASE)
            if m:
                result["cliente"] = m.group(1).strip()
                tipo_raw = m.group(2).strip()
                result["vidas"] = m.group(3)
                # Normalizar tipo
                if "odonto" in tipo_raw.lower() and "saúde" in tipo_raw.lower():
                    result["tipo"] = "Saúde + Odonto"
                elif "odonto" in tipo_raw.lower():
                    result["tipo"] = "Odonto"
                elif "vida" in tipo_raw.lower():
                    result["tipo"] = "Vida"
                else:
                    result["tipo"] = "Saúde"
                continue

            if line.startswith("Corretor"):
                result["vendedor"] = line.replace("Corretor", "").strip()
            elif line.startswith("E-mail"):
                result["email"] = line.replace("E-mail", "").strip()
            elif line.startswith("Telefone"):
                result["telefone"] = line.replace("Telefone", "").strip()

        # ── Extrair tabela de planos ───────────────────────────
        tables = page.extract_tables()
        if not tables:
            return result

        main_table = tables[0]
        if len(main_table) < 4:
            return result

        # Linha 0: Cabeçalhos dos planos (ex: "S2500 R2\nAmil")
        headers = main_table[0]
        num_planos = len(headers)

        # Extrair nome do plano e seguradora de cada coluna
        plano_names = []
        plano_segs = []
        for h in headers:
            if h and "\n" in h:
                parts = h.split("\n")
                plano_names.append(parts[0].strip())
                plano_segs.append(parts[1].strip())
            elif h:
                plano_names.append(h.strip())
                plano_segs.append("")
            else:
                plano_names.append("")
                plano_segs.append("")

        # Linhas seguintes: Modalidade, Acomodação, Coparticipação, faixas, total
        modalidades = main_table[1] if len(main_table) > 1 else [""] * num_planos
        acomodacoes = main_table[2] if len(main_table) > 2 else [""] * num_planos
        coparticipacoes = main_table[3] if len(main_table) > 3 else [""] * num_planos

        # Faixas etárias (linhas entre coparticipação e total)
        # Precisamos do texto para pegar as faixas com qtd
        faixa_rows = []
        total_row = None

        # Buscar faixas no texto (formato: "00 a 18 2 x 1.105,84 961,60 ...")
        for line in lines:
            m = re.match(r'^(\d+\s*a\s*\d+)\s+(\d+\s*x)\s+(.*)', line.strip())
            if m:
                faixa_nome = m.group(1).strip()
                qtd = m.group(2).strip()
                valores = re.findall(r'[\d]+[.,][\d.,]*[\d]', m.group(3))
                faixa_rows.append({"faixa": faixa_nome, "qtd": qtd, "valores": valores})

        # Última linha da tabela é o total
        if len(main_table) > 4:
            total_row = main_table[-1]

        # ── Montar planos ──────────────────────────────────────
        for i in range(num_planos):
            plano = {
                "seguradora": plano_segs[i] if i < len(plano_segs) else "",
                "plano": plano_names[i] if i < len(plano_names) else "",
                "modalidade": (modalidades[i] or "").strip() if i < len(modalidades) else "",
                "acomodacao": (acomodacoes[i] or "").strip() if i < len(acomodacoes) else "",
                "coparticipacao": (coparticipacoes[i] or "").strip() if i < len(coparticipacoes) else "",
                "valor": "",
                "faixas": []
            }

            # Total
            if total_row and i < len(total_row) and total_row[i]:
                plano["valor"] = total_row[i].strip()

            # Faixas
            for fr in faixa_rows:
                valor_faixa = fr["valores"][i] if i < len(fr["valores"]) else ""
                plano["faixas"].append({
                    "faixa": fr["faixa"],
                    "qtd": fr["qtd"],
                    "valor": valor_faixa
                })

            result["planos"].append(plano)

    return result


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "msg": "Uso: python3 extrair_cotacao.py <arquivo.pdf>"}))
        sys.exit(1)

    try:
        data = extrair_cotacao(sys.argv[1])
        print(json.dumps({"ok": True, "data": data}, ensure_ascii=False))
    except Exception as e:
        print(json.dumps({"ok": False, "msg": str(e)}))
