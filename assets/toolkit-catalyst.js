import './styles/toolkit-catalyst.css';
import '@tailwindplus/elements';
import { startStimulusApp } from '@symfony/stimulus-bundle';
import Checkbox from 'catalyst/checkbox/assets/controllers/checkbox_controller.js';

const app = startStimulusApp();
app.register('checkbox', Checkbox);
