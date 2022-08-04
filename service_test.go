package kassiscore

import (
	"github.com/rs/zerolog"
	"github.com/stretchr/testify/assert"
	"os"
	"path/filepath"
	"testing"
)

func TestImportFromFileNCNDLRDF(t *testing.T) {
	solrserveruri := "http://localhost:8983"
	solrcorename := "kassiscore_test"

	err := SolrClearDocument(solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	files := []string{"nonono"}
	err = ImportFromFileNCNDLRDF(files, solrserveruri, solrcorename)
	if err == nil {
		t.Fatal("failed test")
	}

	dir, _ := os.Getwd()
	filepathname := filepath.Join(dir, "testdata", "000462.xml")

	files = []string{filepathname}
	err = ImportFromFileNCNDLRDF(files, solrserveruri, solrcorename)
	if err != nil {
		t.Error(err)
		t.Fatal("failed test")
	}
}

func TestExtnameToMediaTypeSuccess(t *testing.T) {
	result := ExtnameToMediaType("bar")
	if result != "application/octet-stream" {
		t.Fatal("failed test")
	}

	result = ExtnameToMediaType("xlsx")
	if result != "application/octet-stream" {
		t.Fatal("failed test")
	}

	result = ExtnameToMediaType(".xlsx")
	if result != ContenttypeExcel {
		t.Fatal("failed test")
	}
}

func TestSolrQuery(t *testing.T) {
	solrserveruri := "http://localhost:8983"
	solrcorename := "kassiscore_test"
	tikaserveruri := "http://localhost:9998"

	err := SolrClearDocument(solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	dir, _ := os.Getwd()
	filepathname := filepath.Join(dir, "testdata", "Book1.xlsx")

	files := []string{filepathname}
	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	res, err := SolrQuery(solrserveruri, solrcorename, "ぽっぽ焼き")
	if err != nil {
		t.Fatal("failed test (query fail")
	}
	if res.Results.NumFound != 1 {
		t.Errorf("failed test (result num found unmatched) Actual numFound=%d", res.Results.NumFound)
	}
	for _, v := range res.Highlighting {
		v2 := v.(map[string]interface{})["contents"].([]interface{})[0]
		//TODO: ぽっぽ焼き がハイライトさせたい
		assert.Contains(t, v2, "<em>ぽっぽ</em>")
	}
}

func TestImportFromFile(t *testing.T) {
	solrserveruri := "http://localhost:8983"
	solrcorename := "kassiscore_test"
	tikaserveruri := "http://localhost:9998"
	/*
		userInput := "Y"
		funcDefer, err := mockStdin(t, userInput)
		if err != nil {
			t.Fatal(err)
		}
		defer funcDefer()
	*/
	err := SolrClearDocument(solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	files := []string{"nonono"}

	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	//TODO: errメッセージを確認したい（os: Unable to open file ～）
	if err == nil {
		t.Fatal("failed test")
	}

	dir, _ := os.Getwd()
	filepathname := filepath.Join(dir, "testdata", "Book1.xlsx")

	files = []string{filepathname}
	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	res, err := SolrQuery(solrserveruri, solrcorename, "")
	if err != nil {
		t.Fatal("failed test (query fail")
	}
	if res.Results.NumFound != 8 {
		t.Errorf("failed test (result num found unmatched) Actual numFound=%d", res.Results.NumFound)
	}

	dir, _ = os.Getwd()
	filepathname = filepath.Join(dir, "testdata", "Book1.xlsx")
	targetid := filepathname + "データ1"
	if res.Results.Docs[1].Get("materialid").(string) != targetid {
		t.Errorf("failed test unmatch materialid /expected:%s / actual:%s", targetid, res.Results.Docs[1].Get("materialid").(string))
	}

	err = SolrClearDocument(solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}

	filepathname = filepath.Join(dir, "testdata", "shinanogawa.pdf")

	files = []string{filepathname}
	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}
	//TODO: 登録されたデータの内容などを確認したい
	res, err = SolrQuery(solrserveruri, solrcorename, "")
	if err != nil {
		t.Fatal("failed test")
	}
	if res.Results.NumFound != 5 {
		t.Fatal("failed test")
	}

	err = SolrClearDocument(solrserveruri, solrcorename)
	if err != nil {
		return
	}
	filepathname = filepath.Join(dir, "testdata", "shinanogawa.docx")
	files = []string{filepathname}
	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}
	//TODO: 登録されたデータの内容などを確認したい
	res, err = SolrQuery(solrserveruri, solrcorename, "")
	if err != nil {
		t.Fatal("failed test")
	}
	if res.Results.NumFound != 1 {
		t.Fatal("failed test")
	}

	//txtファイルのテスト
	err = SolrClearDocument(solrserveruri, solrcorename)
	if err != nil {
		return
	}
	filepathname = filepath.Join(dir, "testdata", "shinanogawa.txt")
	files = []string{filepathname}
	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}
	//TODO: 登録されたデータの内容などを確認したい
	res, err = SolrQuery(solrserveruri, solrcorename, "")
	if err != nil {
		t.Fatal("failed test")
	}
	if res.Results.NumFound != 1 {
		t.Fatal("failed test")
	}

	_ = SolrClearDocument(solrserveruri, solrcorename)

	dir, _ = os.Getwd()
	filepathname1 := filepath.Join(dir, "testdata", "Book1.xlsx")
	filepathname2 := filepath.Join(dir, "testdata", "shinanogawa.pdf")

	files = []string{filepathname1, filepathname2}
	err = ImportFromFile(files, tikaserveruri, solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}
	res, err = SolrQuery(solrserveruri, solrcorename, "")
	if err != nil {
		t.Fatal("failed test")
	}
	if res.Results.NumFound != 13 {
		t.Fatal("failed test")
	}
}

func TestWebCrawlerInvalidPath(t *testing.T) {

	zerolog.SetGlobalLevel(zerolog.DebugLevel)

	t.Setenv("KASSISCONFIG", "./config.json")

	cfg := LoadConfig()
	result, err := WebCrawler("", cfg)
	assert.Contains(t, result, "ng")
	assert.EqualError(t, err, "Get \"\": unsupported protocol scheme \"\"")

	result, err = WebCrawler("http://www.example.com", cfg)
	assert.Contains(t, result, "ok")
	assert.NoError(t, err)
}

/*
func mockStdin(t *testing.T, dummyInput string) (funcDefer func(), err error) {
	t.Helper()

	oldOsStdin := os.Stdin
	tmpfile, err := ioutil.TempFile(t.TempDir(), t.Name())

	if err != nil {
		return nil, err
	}

	content := []byte(dummyInput)

	if _, err := tmpfile.Write(content); err != nil {
		return nil, err
	}

	if _, err := tmpfile.Seek(0, 0); err != nil {
		return nil, err
	}

	// Set stdin to the temp file
	os.Stdin = tmpfile

	return func() {
		// clean up
		os.Stdin = oldOsStdin
		os.Remove(tmpfile.Name())
	}, nil
}
*/
