import 'react-quill/dist/quill.snow.css';
import { Button, Card, Select, Slider, Space, message } from 'antd';
import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import ReactQuill from 'react-quill';
import { DuplicateFlagReview } from '../components/DuplicateFlagReview';
import { QuestionVersionHistory } from '../components/QuestionVersionHistory';
import { useAuth } from '../context/AuthContext';
import { questionApi } from '../services/questionApi';

export function QuestionEditorPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const auth = useAuth();
  const [contentHtml, setContentHtml] = useState('');
  const [categoryId, setCategoryId] = useState<number | undefined>();
  const [difficulty, setDifficulty] = useState(3);
  const [tagIds, setTagIds] = useState<number[]>([]);
  const [categories, setCategories] = useState<any[]>([]);
  const [tags, setTags] = useState<any[]>([]);
  const [flags, setFlags] = useState<any[]>([]);
  const [versions, setVersions] = useState<any[]>([]);
  const [saving, setSaving] = useState(false);
  const [publishing, setPublishing] = useState(false);

  const load = async () => {
    const [c, t] = await Promise.all([questionApi.listCategories(), questionApi.listTags()]);
    setCategories(c.data.items || []);
    setTags(t.data.items || []);
    if (id) {
      const [d, v] = await Promise.all([questionApi.detail(Number(id)), questionApi.versions(Number(id))]);
      setContentHtml(d.data.current_version?.content_html || '');
      setCategoryId(d.data.category?.id);
      setDifficulty(Number(d.data.current_version?.difficulty || 3));
      setVersions(v.data.items || []);
    }
  };

  useEffect(() => { load(); }, [id]);

  const saveDraft = async () => {
    if (!contentHtml.trim()) {
      message.error('Content is required');
      return;
    }
    if (!categoryId) {
      message.error('Category is required');
      return;
    }
    if (difficulty < 1 || difficulty > 5) {
      message.error('Difficulty must be 1-5');
      return;
    }
    setSaving(true);
    try {
      if (id) {
        await questionApi.update(Number(id), { content_html: contentHtml, category_id: categoryId, difficulty, tags: tagIds });
      } else {
        const response = await questionApi.create({ content_html: contentHtml, category_id: categoryId, difficulty, tags: tagIds });
        navigate(`/questions/${response.data.id}`);
      }
      message.success('Draft saved');
      await load();
    } finally {
      setSaving(false);
    }
  };

  const publish = async (ack = false) => {
    if (!id) return;
    setPublishing(true);
    try {
      const response = await questionApi.publish(Number(id), ack);
      if (response.data.published) {
        message.success('Published');
        setFlags([]);
      } else {
        setFlags(response.data.warnings || []);
        message.warning('Duplicate warnings found');
      }
    } finally {
      setPublishing(false);
    }
  };

  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      <Card title={id ? `Question #${id}` : 'New Question'}>
        <Space direction="vertical" style={{ width: '100%' }}>
          <ReactQuill value={contentHtml} onChange={setContentHtml} theme="snow" />
          <Space wrap>
            <Select placeholder="Category" style={{ width: 220 }} value={categoryId} onChange={setCategoryId} options={categories.map((c) => ({ label: c.name, value: c.id }))} />
            <div style={{ width: 220 }}><Slider min={1} max={5} value={difficulty} onChange={(v) => setDifficulty(Number(v))} /></div>
            <Select mode="multiple" placeholder="Tags" style={{ width: 280 }} value={tagIds} onChange={setTagIds} options={tags.map((t) => ({ label: t.name, value: t.id }))} />
          </Space>
          <Space>
            <Button onClick={saveDraft} loading={saving} disabled={saving}>Save Draft</Button>
            {id ? <Button type="primary" onClick={() => publish(false)} loading={publishing} disabled={publishing}>Publish</Button> : null}
          </Space>
        </Space>
      </Card>

      {flags.length > 0 ? (
        <DuplicateFlagReview
          flags={flags}
          onDismiss={(qid) => setFlags((prev) => prev.filter((f) => f.matched_question_id !== qid))}
          onView={(qid) => navigate(`/questions/${qid}`)}
          onConfirm={() => publish(true)}
        />
      ) : null}

      {id ? <QuestionVersionHistory questionId={Number(id)} versions={versions} allowRollback={auth.user?.role === 'ROLE_SYSTEM_ADMIN'} onDone={load} /> : null}
    </Space>
  );
}
