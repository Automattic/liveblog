import { initialState, user } from '../user';
import { entryEditOpen, entryEditClose } from '../../actions/userActions';

describe('user reducer', () => {
  it('should return the initial state', () => {
    expect(user(undefined, {})).toEqual(initialState);
  });

  const id = 1;
  const stateAfterEntryEditOpen = {
    ...initialState,
    entries: { ...initialState.entries, [id]: { isEditing: true } },
  };

  it('should handle ENTRY_EDIT_OPEN', () => {
    expect(
      user(initialState, entryEditOpen(id)),
    ).toEqual(stateAfterEntryEditOpen);
  });

  const stateAfterEntryEditClose = {
    ...stateAfterEntryEditOpen,
    entries: { ...stateAfterEntryEditOpen.entries, [id]: { isEditing: false } },
  };

  it('should handle ENTRY_EDIT_CLOSE', () => {
    expect(
      user(stateAfterEntryEditOpen, entryEditClose(id)),
    ).toEqual(stateAfterEntryEditClose);
  });
});
