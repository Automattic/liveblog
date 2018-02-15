import { CompositeDecorator } from 'draft-js';

import Editor from './Editor';
import HTMLConvertFrom from './convertFromHTML';
import HTMLConvertTo from './convertToHTML';
import decoratorsArray from './decorators/decorators';

export const decorators = new CompositeDecorator(decoratorsArray);
export const convertFromHTML = HTMLConvertFrom;
export const convertToHTML = HTMLConvertTo;

export default Editor;
