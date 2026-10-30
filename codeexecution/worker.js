// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/* eslint-env worker */
/* global loadPyodide, initRDKitModule */

/**
 * Web worker executing Python code with Pyodide inside the code execution sandbox.
 *
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(() => {
    'use strict';

    const {pyodideUrl, rdkitUrl, schoolAxes} = self.aichatConfig;
    const MAX_TEXT_LENGTH = 20000;
    const NOOP = () => undefined;

    const RUNTIME = `
import base64, builtins, io, math, sys, warnings

# Deprecation notes of the libraries only confuse learners.
warnings.filterwarnings('ignore', category=DeprecationWarning)
warnings.filterwarnings('ignore', message='.*non-interactive.*')

MAX_IMAGES = 10
MAX_IMAGE_BYTES = 5 * 1024 * 1024
MAX_SLIDERS = 10
MAX_ROTATABLE_FIGURES = 5
SCHOOL_AXES = False
_outputs = []
_sliders = []
_slider_values = {}
_figures = {}
_next_handle = 0

def _add_image(mime, data, handle=None, view=None):
    if len(_outputs) >= MAX_IMAGES:
        raise RuntimeError(f'Too many images, at most {MAX_IMAGES} are displayed.')
    if len(data) > MAX_IMAGE_BYTES:
        raise RuntimeError('Image is too large to be displayed.')
    _outputs.append({'mime': mime, 'data': base64.b64encode(data).decode('ascii'), 'handle': handle, 'view': view})

def _axes_3d(figure):
    return [ax for ax in figure.axes if ax.name == '3d']

def _render(figure, dpi=100):
    buffer = io.BytesIO()
    # Without tight bounding box the image size stays constant while a 3D figure is rotated.
    figure.savefig(buffer, format='png', dpi=dpi, bbox_inches=None if _axes_3d(figure) else 'tight')
    return buffer.getvalue()

def _keep_figure(figure):
    global _next_handle
    handle = _next_handle
    _next_handle += 1
    _figures[handle] = figure
    while len(_figures) > MAX_ROTATABLE_FIGURES:
        del _figures[next(iter(_figures))]
    return handle

def rotate(handle, elev, azim, dpi):
    figure = _figures.get(handle)
    if figure is None:
        raise KeyError('The figure is no longer available, please calculate again.')
    for ax in _axes_3d(figure):
        ax.view_init(elev=elev, azim=azim, roll=ax.roll)
    return base64.b64encode(_render(figure, dpi)).decode('ascii')

def _tick_label(value, position):
    return '' if abs(value) < 1e-9 else f'{value:g}'

def _school_axes(ax):
    # Coordinate system as used at school: axes through the origin with arrows, unit ticks and a squared grid.
    from matplotlib.ticker import AutoMinorLocator, FuncFormatter, MaxNLocator, MultipleLocator
    if ax.name != 'rectilinear' or ax.get_label() == '<colorbar>':
        return
    xmin, xmax = ax.get_xlim()
    ymin, ymax = ax.get_ylim()
    ax.set_xlim(min(xmin, -1), max(xmax, 1))
    ax.set_ylim(min(ymin, -1), max(ymax, 1))
    xlabel = ax.get_xlabel() or 'x'
    ylabel = ax.get_ylabel() or 'y'
    ax.set_xlabel('')
    ax.set_ylabel('')
    for axis, (low, high) in ((ax.xaxis, ax.get_xlim()), (ax.yaxis, ax.get_ylim())):
        if high - low <= 12:
            axis.set_major_locator(MultipleLocator(1))
            axis.set_minor_locator(MultipleLocator(0.5))
        else:
            axis.set_major_locator(MaxNLocator(nbins=10, integer=True, steps=[1, 2, 5, 10]))
            axis.set_minor_locator(AutoMinorLocator(2))
        axis.set_major_formatter(FuncFormatter(_tick_label))
    for side in ('left', 'bottom'):
        ax.spines[side].set_position('zero')
        ax.spines[side].set_color('black')
        ax.spines[side].set_linewidth(1.5)
    ax.spines['right'].set_visible(False)
    ax.spines['top'].set_visible(False)
    ax.grid(True, which='both', color='#cccccc', linewidth=0.7)
    ax.set_axisbelow(True)
    ax.plot(1, 0, '>k', markersize=8, transform=ax.get_yaxis_transform(), clip_on=False, label='_nolegend_')
    ax.plot(0, 1, '^k', markersize=8, transform=ax.get_xaxis_transform(), clip_on=False, label='_nolegend_')
    ax.annotate(xlabel, xy=(1, 0), xycoords=ax.get_yaxis_transform(), xytext=(8, 0), textcoords='offset points',
                ha='left', va='center', fontsize=12, fontweight='bold')
    ax.annotate(ylabel, xy=(0, 1), xycoords=ax.get_xaxis_transform(), xytext=(0, 8), textcoords='offset points',
                ha='center', va='bottom', fontsize=12, fontweight='bold')
    if ax.get_title():
        ax.set_title(ax.get_title(), pad=28)
    xspan = ax.get_xlim()[1] - ax.get_xlim()[0]
    yspan = ax.get_ylim()[1] - ax.get_ylim()[0]
    if 0.2 <= yspan / xspan <= 5:
        ax.set_aspect('equal', adjustable='box')
        if len(ax.figure.axes) == 1:
            width = 7
            height = min(max(width * yspan / xspan, 3), 9)
            ax.figure.set_size_inches(height * xspan / yspan if height == 9 else width, height)

def _show(*args, **kwargs):
    import matplotlib.pyplot as plt
    for num in plt.get_fignums():
        figure = plt.figure(num)
        if SCHOOL_AXES:
            for ax in figure.axes:
                _school_axes(ax)
        axes3d = _axes_3d(figure)
        if axes3d:
            _add_image('image/png', _render(figure), _keep_figure(figure), [axes3d[0].elev, axes3d[0].azim])
        else:
            _add_image('image/png', _render(figure))
    plt.close('all')

def show_molecule(smiles, legend='', width=350, height=260):
    import aichat_js
    svg = aichat_js.renderMolecule(str(smiles), int(width), int(height), str(legend))
    if svg is None:
        raise ValueError(f'Invalid SMILES: {smiles}')
    _add_image('image/svg+xml', svg.encode('utf-8'))

builtins.show_molecule = show_molecule

def _register_slider(label, valmin, valmax, valinit, valstep):
    if len(_sliders) >= MAX_SLIDERS:
        raise RuntimeError(f'Too many sliders, at most {MAX_SLIDERS} are supported.')
    if valmax <= valmin:
        raise ValueError('valmax must be greater than valmin.')
    if isinstance(valstep, (int, float)) and valstep > 0:
        step = float(valstep)
    else:
        # A power of ten keeps typical initial values on the grid of the HTML slider.
        step = 10 ** math.floor(math.log10((valmax - valmin) / 100))
    key = f's{len(_sliders)}'
    value = _slider_values.get(key, valinit)
    value = min(max(float(value), valmin), valmax)
    _sliders.append({'key': key, 'label': label, 'min': valmin, 'max': valmax, 'step': step, 'value': value})
    return value

class Slider:
    # Stands in for matplotlib.widgets.Slider: the browser shows an HTML slider and runs the code again on change.
    def __init__(self, ax, label, valmin, valmax, valinit=0.5, valfmt=None, closedmin=True, closedmax=True,
                 slidermin=None, slidermax=None, dragging=True, valstep=None, orientation='horizontal', **kwargs):
        ax.remove()
        self.ax = ax
        self.label = str(label)
        self.valmin = float(valmin)
        self.valmax = float(valmax)
        self.valinit = float(valinit)
        self.valstep = valstep
        self._observers = []
        self.val = _register_slider(self.label, self.valmin, self.valmax, self.valinit, valstep)

    def on_changed(self, func):
        self._observers.append(func)
        if self.val != self.valinit:
            func(self.val)
        return len(self._observers)

    def set_val(self, val):
        self.val = val
        for func in self._observers:
            func(val)

    def reset(self):
        self.set_val(self.valinit)

    def disconnect(self, cid):
        pass

def prepare(matplotlib_loaded, slider_values):
    _outputs.clear()
    _sliders.clear()
    _slider_values.clear()
    _slider_values.update(slider_values)
    if matplotlib_loaded:
        import matplotlib
        matplotlib.use('Agg')
        import matplotlib.pyplot as plt
        import matplotlib.widgets
        plt.close('all')
        plt.show = _show
        matplotlib.widgets.Slider = Slider

def finish():
    try:
        if 'matplotlib.pyplot' in sys.modules:
            _show()
        return list(_outputs), list(_sliders)
    finally:
        _outputs.clear()
        _sliders.clear()
`;

    let pyodidePromise = null;
    let rdkitPromise = null;
    let rdkit = null;
    let runtime = null;
    let queue = Promise.resolve();

    // Returns undefined for invalid SMILES, which arrives as None in Python.
    const renderMolecule = (smiles, width, height, legend) => {
        const mol = rdkit.get_mol(smiles);
        if (!mol) {
            return undefined;
        }
        try {
            if (!mol.is_valid()) {
                return undefined;
            }
            return mol.get_svg_with_highlights(JSON.stringify({width, height, legend}));
        } finally {
            mol.delete();
        }
    };

    const getPyodide = () => {
        if (!pyodidePromise) {
            pyodidePromise = (async() => {
                importScripts(pyodideUrl + 'pyodide.js');
                const pyodide = await loadPyodide({indexURL: pyodideUrl, env: {MPLBACKEND: 'Agg'}});
                pyodide.registerJsModule('aichat_js', {renderMolecule});
                runtime = pyodide.globals.get('dict')();
                pyodide.runPython(RUNTIME, {globals: runtime, filename: 'aichat_runtime.py'});
                runtime.set('SCHOOL_AXES', schoolAxes === true);
                return pyodide;
            })();
            pyodidePromise.catch(() => {
                pyodidePromise = null;
            });
        }
        return pyodidePromise;
    };

    const getRDKit = () => {
        if (!rdkitPromise) {
            rdkitPromise = (async() => {
                importScripts(rdkitUrl + 'RDKit_minimal.js');
                rdkit = await initRDKitModule({locateFile: (file) => rdkitUrl + file});
                return rdkit;
            })();
            rdkitPromise.catch(() => {
                rdkitPromise = null;
            });
        }
        return rdkitPromise;
    };

    const cleanTraceback = (message) => {
        const lines = String(message).split('\n');
        const start = lines.findIndex((line) => line.includes('File "<exec>"'));
        if (start === -1) {
            return message;
        }
        return [lines[0], ...lines.slice(start)].join('\n').trim();
    };

    const run = async(id, code, sliderValues) => {
        self.postMessage({type: 'status', id, status: 'loading'});
        let stdout = '';
        let truncated = false;
        const append = (text) => {
            if (stdout.length + text.length + 1 > MAX_TEXT_LENGTH) {
                stdout = (stdout + text + '\n').substring(0, MAX_TEXT_LENGTH);
                truncated = true;
            } else {
                stdout += text + '\n';
            }
        };
        let error = null;
        let images = [];
        let sliders = [];
        try {
            const pyodide = await getPyodide();
            await pyodide.loadPackagesFromImports(code, {messageCallback: NOOP, errorCallback: NOOP});
            if (/sympy/.test(code) && /\bplot\w*\s*\(/.test(code) && !pyodide.loadedPackages.matplotlib) {
                // SymPy plots need matplotlib although the code does not import it.
                await pyodide.loadPackage('matplotlib', {messageCallback: NOOP, errorCallback: NOOP});
            }
            if (code.includes('show_molecule')) {
                await getRDKit();
            }
            const values = pyodide.toPy(sliderValues);
            runtime.get('prepare')(Boolean(pyodide.loadedPackages.matplotlib), values);
            values.destroy();
            pyodide.setStdout({batched: append});
            pyodide.setStderr({batched: append});
            self.postMessage({type: 'status', id, status: 'running'});
            const globals = pyodide.toPy({__name__: '__main__'});
            try {
                await pyodide.runPythonAsync(code, {globals});
            } catch (exception) {
                error = cleanTraceback(exception.message);
            } finally {
                globals.destroy();
            }
            const outputs = runtime.get('finish')();
            const [imageList, sliderList] = outputs.toJs({dict_converter: Object.fromEntries}); // eslint-disable-line camelcase
            images = imageList;
            sliders = sliderList;
            outputs.destroy();
        } catch (exception) {
            error = error ?? cleanTraceback(exception.message ?? String(exception));
        }
        self.postMessage({type: 'result', id, stdout, truncated, error, images, sliders});
    };

    const rotate = async(id, handle, elev, azim, draft) => {
        let error = null;
        let images = [];
        try {
            await getPyodide();
            const data = runtime.get('rotate')(handle, elev, azim, draft ? 50 : 100);
            images = [{mime: 'image/png', data}];
        } catch (exception) {
            error = cleanTraceback(exception.message ?? String(exception)).split('\n').pop();
        }
        self.postMessage({type: 'result', id, stdout: '', truncated: false, error, images, sliders: []});
    };

    self.addEventListener('message', (event) => {
        const data = event.data;
        if (data && data.type === 'rotate') {
            queue = queue.then(() => rotate(data.id, data.handle, data.elev, data.azim, data.draft));
            return;
        }
        if (!data || data.type !== 'run' || typeof data.code !== 'string') {
            return;
        }
        queue = queue.then(() => run(data.id, data.code, data.sliderValues ?? {}));
    });
})();
