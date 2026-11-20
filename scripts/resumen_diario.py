#!/usr/bin/env python3
"""Escribe el informe diario de Patitas Club a partir de dos archivos TSV de MariaDB.

Uso: resumen_diario.py agenda.tsv recordatorios.tsv carpeta_salida
"""
import csv
import sys
from datetime import date
from pathlib import Path


def leer_tsv(ruta):
    """Devuelve las filas como diccionarios. MariaDB escribe NULL en los campos vacíos."""
    with open(ruta, encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f, delimiter="\t", quoting=csv.QUOTE_NONE)
        return [{k: ("" if v == "NULL" else v) for k, v in fila.items()} for fila in lector]


def bloque_agenda(agenda):
    if not agenda:
        return ["Hoy no hay citas confirmadas."]
    lineas = [f"Citas confirmadas: {len(agenda)}"]
    for c in agenda:
        lineas.append("")
        lineas.append(f"{c['hora']}  {c['perro']} ({c['raza'] or 'sin raza'}) · {c['servicio']}")
        lineas.append(f"       Dueño: {c['cliente']} · {c['telefono']}")
        if c["alergias"]:
            lineas.append(f"       ALERGIAS: {c['alergias']}")
        if c["comportamiento"]:
            lineas.append(f"       CUIDADO: {c['comportamiento']}")
    return lineas


def bloque_recordatorios(recordatorios):
    if not recordatorios:
        return ["Ningún cliente lleva más de 6 semanas sin venir."]
    lineas = []
    for r in recordatorios:
        lineas.append(
            f"- {r['perro']} de {r['cliente']} · {r['telefono']} · "
            f"última visita el {r['ultima_visita']} (hace {r['dias']} días)"
        )
    return lineas


def main():
    if len(sys.argv) != 4:
        sys.exit(__doc__)

    agenda = leer_tsv(sys.argv[1])
    recordatorios = leer_tsv(sys.argv[2])
    hoy = date.today()

    lineas = [f"PATITAS CLUB · Agenda del {hoy:%d/%m/%Y}", "=" * 44, ""]
    lineas += bloque_agenda(agenda)
    lineas += ["", "-" * 44, "Clientes para llamar (más de 6 semanas sin venir)", ""]
    lineas += bloque_recordatorios(recordatorios)

    salida = Path(sys.argv[3]) / f"agenda_{hoy:%Y-%m-%d}.txt"
    # CRLF para que el Bloc de notas de Windows lo abra bien en cualquier versión
    salida.write_text("\n".join(lineas) + "\n", encoding="utf-8", newline="\r\n")
    print(salida)


if __name__ == "__main__":
    main()
