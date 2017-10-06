import { initialState, user } from '../user';
import types from '../../actions/actionTypes';
import * as actions from '../../actions/userActions';

describe('user reducer', () => {
  it('should return the initial state', () => {
    expect(
      user(undefined, {}),
    ).toEqual(initialState);
  });

  it(`should handle ${types.ENTRY_EDIT_OPEN}`, () => {
    const action = actions.entryEditOpen(1);

    expect(
      user(initialState, action),
    ).toEqual({
      entries: {
        1: {
          isEditing: true,
        },
      },
    });
  });

  it(`should handle ${types.ENTRY_EDIT_CLOSE}`, () => {
    const state = {
      entries: {
        1: {
          isEditing: true,
        },
      },
    };

    const action = actions.entryEditClose(1);

    expect(
      user(state, action),
    ).toEqual({
      entries: {
        1: {
          isEditing: false,
        },
      },
    });
  });
});
