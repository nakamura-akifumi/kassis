package solr

func NewDocument(id string) *UpdatedFields {
	fields := make(map[string]interface{})
	fields["id"] = id
	return &UpdatedFields{fields: fields}
}

type UpdatedFields struct {
	fields map[string]interface{}
}

func (f *UpdatedFields) Set(key string, val interface{}) {
	f.fields[key] = map[string]interface{}{"set": val}
}

func (f *UpdatedFields) Add(key string, val interface{}) {
	f.fields[key] = map[string]interface{}{"add": val}
}
