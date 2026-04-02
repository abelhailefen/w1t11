import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { AuthProvider } from '../../frontend/src/context/AuthContext';
import { QuestionEditorPage } from '../../frontend/src/pages/QuestionEditorPage';

jest.mock('react-quill', () => ({
  __esModule: true,
  default: (props: any) => <textarea data-testid="quill" value={props.value} onChange={(e) => props.onChange(e.target.value)} />,
}));
jest.mock('../../frontend/src/services/questionApi', () => ({
  questionApi: {
    listCategories: jest.fn().mockResolvedValue({ data: { items: [] } }),
    listTags: jest.fn().mockResolvedValue({ data: { items: [] } }),
    create: jest.fn().mockResolvedValue({ data: { id: 1 } }),
    detail: jest.fn().mockResolvedValue({ data: {} }),
    versions: jest.fn().mockResolvedValue({ data: { items: [] } }),
  },
}));

describe('QuestionEditorPage', () => {
  it('renders rich editor and save button', async () => {
    render(
      <MemoryRouter initialEntries={['/questions/new']}>
        <AuthProvider>
          <Routes>
            <Route path="/questions/new" element={<QuestionEditorPage />} />
          </Routes>
        </AuthProvider>
      </MemoryRouter>
    );

    expect(await screen.findByTestId('quill')).toBeInTheDocument();
    expect(screen.getByText('Save Draft')).toBeInTheDocument();
  });
});
