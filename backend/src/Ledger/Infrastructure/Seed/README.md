# The PUC seed

`puc.csv` is the official catálogo of the Plan Único de Cuentas (Decreto 2650 de 1993, Art. 6) down to subcuentas:
9 classes, 52 groups, 340 cuentas and 2 118 subcuentas (2 519 accounts), one row per account (`code`, `name`, `nature`). Every new
company gets all of it (`Application/Seed/ProvisionLedger`), plus Mustang's own auxiliares and the default posting
rules (`Application/Seed/MustangChart`).

It was extracted from the reference PDF with `docs/references/extract-puc.py` (its docstring says what it reads, what
it drops and the corrections made by hand where the PDF is wrong or incomplete):

```bash
pdftotext -layout docs/references/PUC-Decreto-2650-1993.pdf /tmp/puc.txt
python3 docs/references/extract-puc.py /tmp/puc.txt > backend/src/Ledger/Infrastructure/Seed/puc.csv
```

Checked for gaps: every class, group and cuenta has its parent; every cuenta named in the decree's "descripciones y
dinámicas" chapter is present (except 3110, which D.R. 2894/94 eliminated); every account PRD Appendix A.10 names is
present, and 4175 runs débito (`PucSeedTest`).
