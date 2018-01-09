import { CompositeDecorator } from 'draft-js';

import Editor from './Editor';
import htmlConverter from './convertFromHTML';
import decoratorsArray from './decorators/decorators';

export const decorators = new CompositeDecorator(decoratorsArray);
export const convertFromHTML = htmlConverter;
export default Editor;
