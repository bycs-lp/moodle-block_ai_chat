Bundled libraries for the Python code execution of block_ai_chat
================================================================

pyodide/
  Subset of the Pyodide 314.0.7 distribution (https://github.com/pyodide/pyodide/releases):
  - pyodide.js, pyodide.asm.mjs, pyodide.asm.wasm, python_stdlib.zip and LICENSE from pyodide-core-<version>.tar.bz2
  - wheels of numpy, matplotlib, sympy and their dependencies from https://cdn.jsdelivr.net/pyodide/v<version>/full/
    (verify the sha256 sums listed in pyodide-lock.json)
  - pyodide-lock.json reduced to exactly the bundled packages (customised), so unknown imports fail with
    ModuleNotFoundError instead of fetching missing files.

rdkit/
  RDKit_minimal.js and RDKit_minimal.wasm from the npm package @rdkit/rdkit (dist/), LICENSE from the RDKit repository.

Update procedure
  1. Replace the files as described above, compute the dependency closure of the bundled packages from the
     "depends" entries of the new pyodide-lock.json.
  2. Bump block_ai_chat\local\code_execution\asset_resolver::REVISION, the files are cached as immutable.
  3. Update thirdpartylibs.xml.
  4. Test plots (matplotlib, sympy) and show_molecule() in the chat.
